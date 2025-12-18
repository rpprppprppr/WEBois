<?php
namespace Legacy\API;

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Entity\ExpressionField;
use Legacy\General\Constants;
use Legacy\Iblock\TestBlockElementTable;

class TestBlock
{
    private const IBLOCK_ID = Constants::IB_TESTBLOCK;

    // Получение всего списка элементов
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

        $query->addSelect('DATE_CREATE');
        $query->addSelect('TIMESTAMP_X');
        $query->addSelect('ACTIVE');
        $query->addSelect('ACTIVE_FROM');
        $query->addSelect('ACTIVE_TO');
        $query->addSelect('SORT');

        $query->addFilter('IBLOCK_ID', self::IBLOCK_ID);
        TestBlockElementTable::withPage($query, $limit, $page);
        $query->setOrder(['ID' => 'ASC']);

        $db = $query->exec();
        $items = [];

        while ($row = $db->fetch()) {
            $items[] = [
                'id'            => $row['ID'],
                'code'          => $row['CODE'],
                'title'         => $row['NAME'],
                'active'        => $row['ACTIVE'],
                'active_from'   => $row['ACTIVE_FROM'] instanceof DateTime ? $row['ACTIVE_FROM']->toString() : null,
                'active_to'     => $row['ACTIVE_TO'] instanceof DateTime ? $row['ACTIVE_TO']->toString() : null,
                'sort'          => $row['SORT'],
                'date_create'   => $row['DATE_CREATE'] instanceof DateTime ? $row['DATE_CREATE']->toString() : null,
                'date_modify'   => $row['TIMESTAMP_X'] instanceof DateTime ? $row['TIMESTAMP_X']->toString() : null,
                'test_property' => $row['PROPERTY_VALUE'] ?? null,
            ];
        }

        $countQuery = TestBlockElementTable::query();
        $countQuery->registerRuntimeField(
            'CNT',
            new ExpressionField('CNT', 'COUNT(%s)', ['ID'])
        );
        $countQuery->addFilter('IBLOCK_ID', self::IBLOCK_ID);
        $countQuery->setSelect(['CNT']);
        $countDb = $countQuery->exec();
        $countRow = $countDb->fetch();
        $totalCount = (int)($countRow['CNT'] ?? 0);

        return [
            'count' => $totalCount,
            'items' => $items
        ];
    }

    // Получение одного элемента по ID
    // /api/TestBlock/getById/
    public static function getById(array $arRequest): ?array
    {
        $id = (int)($arRequest['id'] ?? 0);
        if (!$id) {
            throw new \Exception('Не передан ID элемента');
        }

        if (!\Bitrix\Main\Loader::includeModule('iblock')) {
            throw new \Exception('Модуль iblock не загружен');
        }

        $query = TestBlockElementTable::query();
        TestBlockElementTable::withSelect($query);
        TestBlockElementTable::withRuntimeProperties($query);

        // Подключаем реальные поля
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

        if (!$row) {
            return null;
        }

        return [
            'id'            => $row['ID'],
            'code'          => $row['CODE'],
            'title'         => $row['NAME'],
            'active'        => $row['ACTIVE'],
            'active_from'   => $row['ACTIVE_FROM'] instanceof DateTime ? $row['ACTIVE_FROM']->toString() : null,
            'active_to'     => $row['ACTIVE_TO'] instanceof DateTime ? $row['ACTIVE_TO']->toString() : null,
            'sort'          => $row['SORT'],
            'date_create'   => $row['DATE_CREATE'] instanceof DateTime ? $row['DATE_CREATE']->toString() : null,
            'date_modify'   => $row['TIMESTAMP_X'] instanceof DateTime ? $row['TIMESTAMP_X']->toString() : null,
            'test_property' => $row['PROPERTY_VALUE'] ?? null,
        ];
    }
}