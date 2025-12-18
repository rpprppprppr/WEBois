<?php
namespace Legacy\API;

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Legacy\General\Constants;
use Legacy\Iblock\TestBlockElementTable;

class TestBlock
{
    private const IBLOCK_ID = Constants::IB_TESTBLOCK;

    private static function formatDate(?DateTime $dt): ?string
    {
        return $dt instanceof DateTime ? $dt->toString() : null;
    }

    private static function mapRow(array $row): array
    {
        return [
            'id'            => $row['ID'],
            'code'          => $row['CODE'],
            'title'         => $row['NAME'],
            'active'        => $row['ACTIVE'] ?? null,
            'active_from'   => self::formatDate($row['ACTIVE_FROM'] ?? null),
            'active_to'     => self::formatDate($row['ACTIVE_TO'] ?? null),
            'sort'          => $row['SORT'] ?? null,
            'date_create'   => self::formatDate($row['DATE_CREATE'] ?? null),
            'date_modify'   => self::formatDate($row['TIMESTAMP_X'] ?? null),
            'test_property' => $row['PROPERTY_VALUE'] ?? null,
        ];
    }

    // Получение списка элементов
    // /api/TestBlock/getElements/
    public static function getElements(array $arRequest = []): array
    {
        if (!Loader::includeModule('iblock')) {
            throw new \Exception('Модуль iblock не загружен');
        }

        $limit = (int)($arRequest['limit'] ?? 20);
        $page  = (int)($arRequest['page'] ?? 1);

        $query = TestBlockElementTable::query();
        TestBlockElementTable::withSelect($query);
        TestBlockElementTable::withRuntimeProperties($query);

        // Добавляем каждое поле отдельно
        $query->addSelect('DATE_CREATE');
        $query->addSelect('TIMESTAMP_X');
        $query->addSelect('ACTIVE');
        $query->addSelect('ACTIVE_FROM');
        $query->addSelect('ACTIVE_TO');
        $query->addSelect('SORT');

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

        $query->addSelect('DATE_CREATE');
        $query->addSelect('TIMESTAMP_X');
        $query->addSelect('ACTIVE');
        $query->addSelect('ACTIVE_FROM');
        $query->addSelect('ACTIVE_TO');
        $query->addSelect('SORT');

        $query->addFilter('IBLOCK_ID', self::IBLOCK_ID);
        $query->addFilter('ID', $id);

        $db = $query->exec();
        $row = $db->fetch();

        return $row ? self::mapRow($row) : null;
    }
}