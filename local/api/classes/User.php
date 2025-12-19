<?php
namespace Legacy\API;

use CUser;
use Legacy\General\Constants;

class User
{
    private static function checkAuth(): int
    {
        $userId = UserMapper::getCurrentUserId();

        if ($userId <= 0) {
            throw new \Exception('Пользователь не авторизован');
        }

        return $userId;
    }

    private static function checkAdmin(): void
    {
        self::checkAuth();

        if (!in_array(Constants::GROUP_ADMINS, UserMapper::getCurrentUserGroups())) {
            throw new \Exception('Доступ запрещен: требуется роль администратора');
        }
    }


    // Создание пользователя
    // /api/User/add/?login=test&password=123123&email=test@mail.ru&role=student
    public static function add(array $arData): array
    {
        self::checkAdmin();

        $login    = trim($arData['login'] ?? '');
        $password = trim($arData['password'] ?? '');
        $email    = trim($arData['email'] ?? '');
        $role     = strtolower(trim($arData['role'] ?? ''));

        if (CUser::GetByLogin($login)->Fetch()) {
            throw new \Exception('Пользователь с таким логином уже существует');
        }

        if (!$login || !$password || !$email || !$role) {
            throw new \Exception('Не переданы обязательные параметры: login, password, email, role');
        }

        switch ($role) {
            case 'student':
                $groupId = Constants::GROUP_STUDENTS;
                break;

            case 'teacher':
                $groupId = Constants::GROUP_TEACHERS;
                break;

            default:
                throw new \Exception('Некорректная роль. Допустимые значения: student, teacher');
        }

        $user = new CUser();
        $userId = $user->Add([
            'LOGIN'            => $login,
            'PASSWORD'         => $password,
            'CONFIRM_PASSWORD' => $password,
            'EMAIL'            => $email,
            'GROUP_ID'         => [$groupId],
            'ACTIVE'           => 'Y',
        ]);

        if (!$userId) {
            throw new \Exception('Не удалось создать пользователя: ' . $user->LAST_ERROR);
        }

        return UserMapper::map(
            CUser::GetByID($userId)->Fetch(),
            true
        );
    }

    // Удаление пользователя
    // /api/User/delete/?id=5
    public static function delete(array $arData): bool
    {
        self::checkAdmin();

        $userId = (int)($arData['id'] ?? 0);
        if (!$userId) {
            throw new \Exception('Не передан ID пользователя');
        }

        if ($userId === UserMapper::getCurrentUserId()) {
            throw new \Exception('Нельзя удалить самого себя');
        }

        $user = new CUser();
        if (!$user->Delete($userId)) {
            throw new \Exception('Не удалось удалить пользователя');
        }

        return true;
    }

    private static function getList(array $arRequest = []): array
    {
        $filter = $arRequest['filter'] ?? [];
        $limit  = (int)($arRequest['limit'] ?? 20);
        $page   = (int)($arRequest['page'] ?? 1);

        $navParams = ['nPageSize' => $limit, 'iNumPage' => $page];
        $rsUsers = CUser::GetList('ID', 'ASC', $filter, $navParams);

        $users = [];
        while ($arUser = $rsUsers->Fetch()) {
            $users[] = UserMapper::map($arUser);
        }

        $total = CUser::GetList('ID', 'ASC', $filter)->SelectedRowsCount();

        return [
            'count' => count($users),
            'total' => $total,
            'items' => $users
        ];
    }

    // Получение всех пользователей
    // /api/User/get/
    public static function get(array $arRequest = []): array
    {
        self::checkAdmin();

        return self::getList($arRequest);
    }

    // Получение пользователя по ID
    // /api/User/getById/?id=
    public static function getById(array $arRequest): ?array
    {
        self::checkAdmin();

        $userId = (int)($arRequest['id'] ?? 0);
        if (!$userId) throw new \Exception('Не передан ID пользователя');

        $rsUser = CUser::GetByID($userId);
        $arUser = $rsUser->Fetch();

        return $arUser ? UserMapper::map($arUser) : null;
    }

    // Получение преподавателей
    // /api/User/getTeachers/
    public static function getTeachers(array $arRequest = []): array
    {
        self::checkAdmin();

        return self::getList(array_merge($arRequest, ['filter' => ['GROUPS_ID' => Constants::GROUP_TEACHERS]]));
    }

    // Получение студентов
    // /api/User/getStudents/
    public static function getStudents(array $arRequest = []): array
    {
        self::checkAdmin();

        return self::getList(array_merge($arRequest, ['filter' => ['GROUPS_ID' => Constants::GROUP_STUDENTS]]));
    }
}