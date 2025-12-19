<?php
namespace Legacy\API;

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;

use Legacy\General\Constants;
use Legacy\Iblock\TestBlockTable;

class TestBlock
{
    private const IBLOCK_ID = Constants::IB_TESTBLOCKS;

    private static function mapRow(array $row): array
    {
        return [
            'ID'            => $row['ID'],
            'CODE'          => $row['CODE'],
            'NAME'         => $row['NAME'],
            'ACTIVE'        => $row['ACTIVE'] ?? null,
            'ACTIVE_FROM'   => $row['ACTIVE_FROM'] instanceof DateTime ? $row['ACTIVE_FROM']->toString() : null,
            'ACTIVE_TO'     => $row['ACTIVE_TO'] instanceof DateTime ? $row['ACTIVE_TO']->toString() : null,
            'DATE_CREATE'   => $row['DATE_CREATE'] instanceof DateTime ? $row['DATE_CREATE']->toString() : null,
            'DATE_MODIFY'   => $row['TIMESTAMP_X'] instanceof DateTime ? $row['TIMESTAMP_X']->toString() : null,
            'PROPERTY_VALUE' => $row['PROPERTY_VALUE'] ?? null,
            'SORT'          => $row['SORT'] ?? null,
        ];
    }

    // Получение списка элементов
    // /api/TestBlock/get/
    public static function get(array $arRequest = []): array
    {
        if (!Loader::includeModule('iblock')) {
            throw new \Exception('Модуль iblock не загружен');
        }

        $limit = (int)($arRequest['limit'] ?? 20);
        $page  = (int)($arRequest['page'] ?? 1);

        $query = TestBlockTable::query();
        TestBlockTable::withSelect($query);
        TestBlockTable::withRuntimeProperties($query);

        $query->addFilter('IBLOCK_ID', self::IBLOCK_ID);
        TestBlockTable::withPage($query, $limit, $page);
        $query->setOrder(['ID' => 'ASC']);

        $items = [];
        $db = $query->exec();
        while ($row = $db->fetch()) {
            $items[] = self::mapRow($row);
        }

        return [
            'count' => count($items),
            'items' => $items
        ];
    }

    // Получение одного элемента по ID
    // /api/TestBlock/getById/?id=
    public static function getById(array $arRequest): ?array
    {
        $id = (int)($arRequest['id'] ?? 0);
        if (!$id) {
            throw new \Exception('Не передан ID элемента');
        }

        if (!Loader::includeModule('iblock')) {
            throw new \Exception('Модуль iblock не загружен');
        }

        $query = TestBlockTable::query();
        TestBlockTable::withSelect($query);
        TestBlockTable::withRuntimeProperties($query);

        $query->addFilter('IBLOCK_ID', self::IBLOCK_ID);
        $query->addFilter('ID', $id);

        $db = $query->exec();
        $row = $db->fetch();

        if (!$row) {
            throw new \Exception('Тестовый блок с таким ID не найден');
        }
        return self::mapRow($row);
    }

    // Получение одного элемента по CODE
    // /api/TestBlock/getByCode/?code=
    public static function getByCode(array $arRequest): ?array
    {
        $code = (string)($arRequest['code'] ?? '');
        if (!$code) {
            throw new \Exception('Не передан CODE элемента');
        }

        if (!Loader::includeModule('iblock')) {
            throw new \Exception('Модуль iblock не загружен');
        }

        $query = TestBlockTable::query();
        TestBlockTable::withSelect($query);
        TestBlockTable::withRuntimeProperties($query);

        $query->addFilter('IBLOCK_ID', self::IBLOCK_ID);
        $query->addFilter('CODE', $code);

        $db = $query->exec();
        $row = $db->fetch();

        if (!$row) {
            throw new \Exception('Тестовый блок с таким CODE не найден');
        }
        return self::mapRow($row);
    }
}