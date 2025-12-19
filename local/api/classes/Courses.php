<?php
namespace Legacy\API;

use Bitrix\Main\Loader;
use Legacy\Iblock\CoursesTable;
use Legacy\General\Constants;

class Courses
{
    private static function mapDescription(?string $description): string
    {
        if (empty($description)) {
            return '';
        }

        $data = @unserialize($description);
        if ($data !== false && isset($data['TEXT'])) {
            return $data['TEXT'];
        }

        return $description;
    }

    private static function mapRow(array $row, bool $fullInfo = false): array
    {
        // Автор
        $author = null;
        if (!empty($row['AUTHOR_ID'])) {
            $authorData = User::getById(['id' => (int)$row['AUTHOR_ID']]);
            $author = $fullInfo ? $authorData : [
                'ID' => $authorData['ID'] ?? 0,
                'FIRST_NAME' => $authorData['FIRST_NAME'] ?? '',
                'LAST_NAME' => $authorData['LAST_NAME'] ?? '',
                'SECOND_NAME' => $authorData['SECOND_NAME'] ?? '',
            ];
        }

        // Студенты (только при полном доступе)
        $students = [];
        if ($fullInfo && !empty($row['STUDENT_ID'])) {
            $studentIds = is_array($row['STUDENT_ID']) ? $row['STUDENT_ID'] : [$row['STUDENT_ID']];
            foreach ($studentIds as $id) {
                $s = User::getById(['id' => (int)$id]);
                if ($s) {
                    $students[] = $fullInfo ? $s : [
                        'ID' => $s['ID'],
                        'FIRST_NAME' => $s['FIRST_NAME'],
                        'LAST_NAME' => $s['LAST_NAME'],
                        'SECOND_NAME' => $s['SECOND_NAME']
                    ];
                }
            }
        }

        return [
            'ID' => $row['ID'],
            'NAME' => $row['NAME'] ?? '',
            'DESCRIPTION' => self::mapDescription($row['DESCRIPTION'] ?? ''),
            'AUTHOR' => $author,
            'MODULES_COUNT' => 0,
            'STUDENTS' => $fullInfo ? $students : null,
            'STUDENTS_COUNT' => $fullInfo ? count($students) : null,
        ];
    }

    private static function getList(array $arRequest = [], array $filter = []): array
    {
        if (!Loader::includeModule('iblock')) {
            throw new \Exception('Не удалось подключить модуль iblock');
        }

        $limit = (int)($arRequest['limit'] ?? 20);
        $page = (int)($arRequest['page'] ?? 1);

        $query = CoursesTable::query();
        CoursesTable::withSelect($query);
        CoursesTable::withRuntimeProperties($query);
        CoursesTable::withFilter($query, $filter);
        CoursesTable::withOrder($query);
        CoursesTable::withPage($query, $limit, $page);

        $courses = [];
        $db = $query->exec();
        while ($row = $db->fetch()) {
            $courses[] = $row;
        }

        return [
            'count' => count($courses),
            'items' => $courses
        ];
    }

    // Получение всех доступных курсов
    // /api/Courses/get/
    public static function get(array $arRequest = []): array
    {
        $userId = UserMapper::getCurrentUserId();
        if (!$userId) throw new \Exception('Неавторизованный пользователь');

        $userGroups = UserMapper::getCurrentUserGroups();
        $filter = [];
        $fullInfo = false;

        if (in_array(Constants::GROUP_ADMINS, $userGroups)) {
            $fullInfo = true;
        } elseif (in_array(Constants::GROUP_TEACHERS, $userGroups)) {
            $filter['AUTHOR_PROP.VALUE'] = $userId;
            $fullInfo = true;
        } elseif (in_array(Constants::GROUP_STUDENTS, $userGroups)) {
            $filter['STUDENTS_PROP.VALUE'] = $userId;
        } else {
            $filter['ID'] = 0; // никто не видит
        }

        $result = self::getList($arRequest, $filter);
        $result['items'] = array_map(fn($row) => self::mapRow($row, $fullInfo), $result['items']);

        return $result;
    }

    // Получение курсов по ID
    // /api/Courses/getById/?id=
    public static function getById(array $arRequest): ?array
    {
        $id = (int)($arRequest['id'] ?? 0);
        if (!$id) throw new \Exception('Не передан ID курса');

        $userId = UserMapper::getCurrentUserId();
        if (!$userId) throw new \Exception('Неавторизованный пользователь');

        $userGroups = UserMapper::getCurrentUserGroups();
        $fullInfo = in_array(Constants::GROUP_ADMINS, $userGroups) || in_array(Constants::GROUP_TEACHERS, $userGroups);

        $result = self::getList([], ['ID' => $id]);
        if (empty($result['items'])) throw new \Exception('Курс с таким ID не найден');

        $course = $result['items'][0];

        // Контроль доступа
        if (in_array(Constants::GROUP_ADMINS, $userGroups)) {
            // Админ видит всё
        } elseif (in_array(Constants::GROUP_TEACHERS, $userGroups)) {
            if ((int)$course['AUTHOR_ID'] !== $userId) {
                throw new \Exception('Доступ запрещен: это не ваш курс');
            }
        } elseif (in_array(Constants::GROUP_STUDENTS, $userGroups)) {
            $studentIds = is_array($course['STUDENT_ID']) ? $course['STUDENT_ID'] : [$course['STUDENT_ID']];
            if (!in_array($userId, $studentIds)) {
                throw new \Exception('Доступ запрещен: вы не являетесь студентом этого курса');
            }
            $fullInfo = false;
        } else {
            throw new \Exception('Доступ запрещен');
        }

        return [self::mapRow($course, $fullInfo)];
    }

    // Получение курсов по преподавателю (ADMIN)
    // /api/Courses/getByTeacher/?id=
    public static function getByTeacher(array $arRequest): array
    {
        return self::getByRole($arRequest, 'AUTHOR_PROP.VALUE');
    }

    // Получение курсов по студенту (ADMIN)
    // /api/Courses/getByStudent/?id=
    public static function getByStudent(array $arRequest): array
    {
        return self::getByRole($arRequest, 'STUDENTS_PROP.VALUE');
    }

    private static function getByRole(array $arRequest, string $roleProp): array
    {
        $userGroups = UserMapper::getCurrentUserGroups();
        if (!in_array(Constants::GROUP_ADMINS, $userGroups)) {
            throw new \Exception('Доступ запрещен: необходимо иметь роль администратора');
        }

        $id = (int)($arRequest['id'] ?? $_GET['id'] ?? 0);
        if (!$id) throw new \Exception("Не указан ID для роли {$roleProp}");

        $result = self::getList($arRequest, [$roleProp => $id]);
        $result['items'] = array_map(fn($row) => self::mapRow($row, true), $result['items']);

        return $result;
    }

    // Добавление курса
    // /api/Courses/add/?name=1&description=1&code=1
    public static function add(array $arData): array
    {
        $userId = UserMapper::getCurrentUserId();
        if (!$userId) throw new \Exception('Неавторизованный пользователь');

        $userGroups = UserMapper::getCurrentUserGroups();
        if (!in_array(Constants::GROUP_ADMINS, $userGroups) && !in_array(Constants::GROUP_TEACHERS, $userGroups)) {
            throw new \Exception('Доступ запрещен: только админ или преподаватель');
        }

        $name = trim($arData['name'] ?? '');
        $description = $arData['description'] ?? '';
        $code = trim($arData['code'] ?? '');

        if ($name === '' || $description === '' || $code === '') {
            throw new \Exception('Обязательные поля: NAME, DESCRIPTION, CODE');
        }

        if (in_array(Constants::GROUP_TEACHERS, $userGroups)) {
            $authorId = $userId;
        } elseif (in_array(Constants::GROUP_ADMINS, $userGroups)) {
            $authorId = (int)($arData['author_id'] ?? $userId);
        }

        if (!Loader::includeModule('iblock')) throw new \Exception('Не удалось подключить модуль iblock');

        $author = User::getById(['id' => $authorId]);
        if (!$author) throw new \Exception('Автор не найден');

        $authorGroups = UserMapper::getCurrentUserGroups($author['ID'] ?? 0);
        if (!in_array(Constants::GROUP_TEACHERS, $authorGroups)) {
            throw new \Exception('Нельзя указать автора, который не является преподавателем');
        }

        $fields = [
            'NAME' => $name,
            'DESCRIPTION' => self::mapDescription($description),
            'CODE' => $code,
            'PROP_AUTHOR' => $author,
            'STUDENT_ID' => [],
        ];

        $id = CoursesTable::addCourse($fields);
        if (!$id) throw new \Exception('Не удалось создать курс');

        $course = self::getById(['id' => $id])[0];

        return $course;
    }

    // Добавление студента в курс
    // /api/Courses/addStudent/?course_id=1&student_id=2
    public static function addStudent(array $arData): array
    {
        $courseId = (int)($arData['course_id'] ?? 0);
        $studentId = (int)($arData['student_id'] ?? 0);
        if (!$courseId || !$studentId) {
            throw new \Exception('Не передан course_id или student_id');
        }

        $userId = UserMapper::getCurrentUserId();
        $userGroups = UserMapper::getCurrentUserGroups();

        if (!in_array(Constants::GROUP_ADMINS, $userGroups) && !in_array(Constants::GROUP_TEACHERS, $userGroups)) {
            throw new \Exception('Доступ запрещен: только админ или преподаватель');
        }

        $course = self::getById(['id' => $courseId])[0];

        if (in_array(Constants::GROUP_TEACHERS, $userGroups) && $course['AUTHOR']['ID'] != $userId) {
            throw new \Exception('Доступ запрещен: это не ваш курс');
        }

        $student = User::getById(['id' => $studentId]);
        if (!$student) throw new \Exception('Студент не найден');

        $students = $course['STUDENTS'] ? array_column($course['STUDENTS'], 'ID') : [];

        if (in_array($studentId, $students)) {
            throw new \Exception('Студент уже добавлен в курс');
        }

        $students[] = $studentId;
        \CIBlockElement::SetPropertyValuesEx($courseId, Constants::IB_COURSES, [
            Constants::PROP_STUDENTS => $students
        ]);

        return self::getById(['id' => $courseId])[0];
    }

    // Удаление студента из курса
    // /api/Courses/removeStudent/?course_id=1&student_id=2
    public static function removeStudent(array $arData): array
    {
        $courseId = (int)($arData['course_id'] ?? 0);
        $studentId = (int)($arData['student_id'] ?? 0);
        if (!$courseId || !$studentId) {
            throw new \Exception('Не передан course_id или student_id');
        }

        $userId = UserMapper::getCurrentUserId();
        $userGroups = UserMapper::getCurrentUserGroups();

        if (!in_array(Constants::GROUP_ADMINS, $userGroups) && !in_array(Constants::GROUP_TEACHERS, $userGroups)) {
            throw new \Exception('Доступ запрещен: только админ или преподаватель');
        }

        $course = self::getById(['id' => $courseId])[0];

        if (in_array(Constants::GROUP_TEACHERS, $userGroups) && $course['AUTHOR']['ID'] != $userId) {
            throw new \Exception('Доступ запрещен: это не ваш курс');
        }

        $students = $course['STUDENTS'] ? array_column($course['STUDENTS'], 'ID') : [];

        if (!in_array($studentId, $students)) {
            throw new \Exception('Студента нет в курсе');
        }

        $students = array_values(array_filter($students, fn($id) => $id != $studentId));

        \CIBlockElement::SetPropertyValuesEx(
            $courseId,
            Constants::IB_COURSES,
            [Constants::PROP_STUDENTS => $students ?: false]
        );

        return self::getById(['id' => $courseId])[0];
    }
}