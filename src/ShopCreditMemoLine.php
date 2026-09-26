<?php

namespace SilverShop\Invoicing;

use SilverShop\Model\Modifiers\OrderModifier;
use SilverShop\Model\OrderItem;
use SilverShop\ORM\FieldType\ShopCurrency;
use SilverStripe\ORM\DataObject;

/**
 * One snapshot line on a {@link ShopCreditMemo} — the refund counterpart of {@link ShopInvoiceLine}.
 *
 * @property string $Kind
 * @property string $Title
 * @property float  $Quantity
 * @property float  $Amount
 */
class ShopCreditMemoLine extends DataObject
{
    private static string $table_name = 'ShopCreditMemoLine';

    private static array $db = [
        'Kind' => "Enum('Item,Modifier','Item')",
        'Title' => 'Varchar(255)',
        'SKU' => 'Varchar(100)',
        'Quantity' => 'Decimal(12,2)',
        'UnitAmount' => ShopCurrency::class,
        'Amount' => ShopCurrency::class,
        'Sort' => 'Int',
    ];

    private static array $has_one = [
        'CreditMemo' => ShopCreditMemo::class,
        'OrderItem' => OrderItem::class,
        'OrderModifier' => OrderModifier::class,
    ];

    private static string $default_sort = '"Sort" ASC, "ID" ASC';

    private static array $summary_fields = [
        'Title' => 'Description',
        'Quantity' => 'Qty',
        'Amount.Nice' => 'Amount',
    ];
}
