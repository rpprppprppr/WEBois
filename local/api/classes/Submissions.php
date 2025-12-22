<?php
namespace Legacy\API;

use Legacy\API\Access\UserAccess;
use Legacy\API\Access\SubmissionsAccess;

use Legacy\HighLoadBlock\SubmissionsTable;
use Legacy\Iblock\ModulesTable;

use Bitrix\Main\Type\DateTime;

class Submissions
{
    private static function mapRow(array $row): array
    {
        $fileIds = [];
        if (!empty($row['UF_FILE'])) {
            $fileIds = is_array($row['UF_FILE'])
                ? array_map('intval', $row['UF_FILE'])
                : [(int)$row['UF_FILE']];
        }

        return Mappers::mapSubmission([
            'ID' => $row['ID'],
            'STUDENT' => !empty($row['UF_STUDENT_ID'])
                ? UserAccess::getUserById((int)$row['UF_STUDENT_ID'])
                : null,
            'MODULE' => !empty($row['UF_MODULE'])
                ? ModulesTable::getModuleById((int)$row['UF_MODULE'])
                : null,
            'UF_SCORE' => $row['UF_SCORE'] ?? null,
            'UF_STATUS' => $row['UF_STATUS'] ?? null,
            'UF_ANSWER' => $row['UF_ANSWER'] ?? null,
            'UF_LINK' => $row['UF_LINK'] ?? null,
            'UF_REVIEW_COMMENT' => $row['UF_REVIEW_COMMENT'] ?? null,
            'UF_DATE_SUBMITTED' => $row['UF_DATE_SUBMITTED'] ?? null,
            'FILES' => $fileIds,
        ]);
    }

    // Получение всех работ (для препода работы по его курсу, для студена его личные работы)
    // /api/Submissions/get/
    public static function get(array $arRequest = []): array
    {
        $userId = UserAccess::checkAuth();
        $role   = UserAccess::getUserRole();

        $filter = [];

        if (!empty($arRequest['module_id'])) {
            $filter['UF_MODULE'] = (int)$arRequest['module_id'];
        }

        if ($role === 'student') {
            $filter['UF_STUDENT_ID'] = $userId;
        }

        if ($role === 'teacher') {
            $allowedModules = SubmissionsAccess::getAllowedModuleIds();
            if (empty($allowedModules)) return ['count' => 0, 'items' => []];
            $filter['UF_MODULE'] = $allowedModules;
        }

        $list = SubmissionsTable::getList(['filter' => $filter]);

        $items = [];
        foreach ($list as $row) {
            try {
                SubmissionsAccess::checkSubmissionOwnership($row);
                $items[] = self::mapRow($row);
            } catch (\Exception $e) {
                continue;
            }
        }

        return [
            'count' => count($items),
            'items' => $items
        ];
    }

    // Получение всех работ (для препода работы по его курсу, для студена его личные работы)
    // /api/Submissions/getById/?submission_id=
    public static function getById(array $arRequest): ?array
    {
        $id = SubmissionsAccess::requireSubmissionId($arRequest);
        $row = SubmissionsTable::getRow(['filter' => ['ID' => $id]]);
        if (!$row) throw new \Exception('Submission не найден');

        SubmissionsAccess::checkSubmissionOwnership($row);

        return self::mapRow($row);
    }

    // Сдача работы
    // /api/Submissions/add/
    public static function add(array $arData): array
    {
        $userId = SubmissionsAccess::assertStudent();

        $moduleId = (int)($arData['module_id'] ?? 0);
        $answer = trim($arData['answer'] ?? '');
        $link = trim($arData['link'] ?? '');

        if (!$moduleId || !$answer) throw new \Exception('Не указан module_id или answer');

        SubmissionsAccess::assertModuleStudentAccess($moduleId, $userId);

        $module = ModulesTable::getModuleById($moduleId);
        if (!$module) throw new \Exception('Модуль не найден');
        if (mb_strtolower($module['TYPE'] ?? '') === 'lesson') {
            throw new \Exception('Невозможно сдать модуль: тип модуля "Урок"');
        }

        if (!empty($module['UF_DEADLINE'])) {
            $now = new DateTime();
            $deadline = new DateTime($module['UF_DEADLINE']);
            if ($now > $deadline) {
                throw new \Exception('Сдать работу невозможно: срок сдачи истек');
            }
        }

        $fields = [
            'UF_STUDENT_ID' => $userId,
            'UF_MODULE' => $moduleId,
            'UF_ANSWER' => $answer,
            'UF_LINK' => $link ?: $answer,
            'UF_DATE_SUBMITTED' => new DateTime(),
            'UF_STATUS' => 1,
        ];

        $submissionId = SubmissionsTable::add($fields);

        return [
            'success' => true,
            'ID' => $submissionId,
            'message' => 'Работа успешно сдана',
        ];
    }

    // Сдача работы
    // /api/Submissions/review/
    public static function review(array $arData): array
    {
        $userId = SubmissionsAccess::assertTeacherOrAdmin();
        $id = SubmissionsAccess::requireSubmissionId($arData);

        $row = SubmissionsTable::getRow(['filter' => ['ID' => $id]]);
        if (!$row) throw new \Exception('Submission не найден');

        SubmissionsAccess::checkSubmissionOwnership($row);

        $module = ModulesTable::getModuleById((int)$row['UF_MODULE']);
        if (!$module) throw new \Exception('Модуль не найден');

        $maxScore = (int)($module['MAX_SCORE'] ?? 0);

        if (($row['UF_STATUS'] ?? 0) == 2) throw new \Exception('Невозможно оценить работу: статус "Оценен"');
        if (!empty($row['UF_SCORE'])) throw new \Exception('Невозможно оценить работу: оценка уже выставлена');

        $fields = [];

        if (isset($arData['score'])) {
            $score = (int)$arData['score'];
            if ($score < 0 || ($maxScore > 0 && $score > $maxScore)) {
                throw new \Exception("Невозможно выставить оценку: минимум 0, максимум — {$maxScore}");
            }
            $fields['UF_SCORE'] = $score;
            $fields['UF_STATUS'] = $score > 0 ? 2 : 1;
        }

        if (isset($arData['review_comment'])) {
            $fields['UF_REVIEW_COMMENT'] = trim((string)$arData['review_comment']);
        }

        $success = SubmissionsTable::update($id, $fields);

        return [
            'success' => $success,
            'message' => 'Submission успешно обновлен',
        ];
    }
}