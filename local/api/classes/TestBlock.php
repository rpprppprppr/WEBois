<?php
namespace Legacy\API;

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;

use Legacy\General\Constants;
use Legacy\Iblock\TestBlockElementTable;

class TestBlock
{
    private const IBLOCK_ID = Constants::IB_TESTBLOCK;

    private static function mapRow(array $row): array
    {
        return [
            'id'            => $row['ID'],
            'code'          => $row['CODE'],
            'title'         => $row['NAME'],
            'active'        => $row['ACTIVE'] ?? null,
            'active_from'   => $row['ACTIVE_FROM'] instanceof DateTime ? $row['ACTIVE_FROM']->toString() : null,
            'active_to'     => $row['ACTIVE_TO'] instanceof DateTime ? $row['ACTIVE_TO']->toString() : null,
            'date_create'   => $row['DATE_CREATE'] instanceof DateTime ? $row['DATE_CREATE']->toString() : null,
            'date_modify'   => $row['TIMESTAMP_X'] instanceof DateTime ? $row['TIMESTAMP_X']->toString() : null,
            'test_property' => $row['PROPERTY_VALUE'] ?? null,
            'sort'          => $row['SORT'] ?? null,
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

        $query = TestBlockElementTable::query();
        TestBlockElementTable::withSelect($query);
        TestBlockElementTable::withRuntimeProperties($query);

        $query->addFilter('IBLOCK_ID', self::IBLOCK_ID);
        TestBlockElementTable::withPage($query, $limit, $page);
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

        $query = TestBlockElementTable::query();
        TestBlockElementTable::withSelect($query);
        TestBlockElementTable::withRuntimeProperties($query);

        $query->addFilter('IBLOCK_ID', self::IBLOCK_ID);
        $query->addFilter('ID', $id);

        $db = $query->exec();
        $row = $db->fetch();

        return $row ? self::mapRow($row) : null;
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

        $query = TestBlockElementTable::query();
        TestBlockElementTable::withSelect($query);
        TestBlockElementTable::withRuntimeProperties($query);

        $query->addFilter('IBLOCK_ID', self::IBLOCK_ID);
        $query->addFilter('CODE', $code);

        $db = $query->exec();
        $row = $db->fetch();

        return $row ? self::mapRow($row) : null;
    }
}