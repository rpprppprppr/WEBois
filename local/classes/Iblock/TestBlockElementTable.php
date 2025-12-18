<?php

namespace Legacy\Iblock;

use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Iblock\ElementPropertyTable;
use Bitrix\Main\DB\SqlExpression;
use Bitrix\Main\Entity\Query;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\Entity\ExpressionField;
use Legacy\General\Constants;

class TestBlockElementTable extends ElementTable
{
    public static function withSelect(Query $query)
    {
        $query->setSelect([
            'ID',
            'NAME',
            'CODE',
            'DATE_CREATE'
        ]);
    }

    public static function withRuntimeProperties(Query $query)
    {
        $query->registerRuntimeField(
            'PROPERTY',
            new ReferenceField(
                'PROPERTY',
                ElementPropertyTable::class,
                [
                    'ref.IBLOCK_ELEMENT_ID' => 'this.ID',
                    'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', Constants::TEST_PROPERTY)
                ]
            )
        );

        $query->addSelect('PROPERTY.VALUE', 'PROPERTY_VALUE');
    }

    public static function withPage(Query $query, $limit, $page)
    {
        $query->setLimit($limit);
        $query->setOffset(($page - 1) * $limit);
    }
}