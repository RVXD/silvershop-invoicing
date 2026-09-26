<?php

namespace SilverShop\Invoicing;

use SilverShop\Model\Modifiers\OrderModifier;
use SilverShop\Model\OrderItem;
use SilverShop\ORM\FieldType\ShopCurrency;
use SilverStripe\ORM\DataObject;

/**
 * One snapshot line on a {@link ShopInvoice}: either a product line (from an OrderItem) or an
 * adjustment line (from an OrderModifier — discount / tax / shipping / any modifier module). Copied
 * at invoice time so it never changes even if the order is later edited.
 *
 * @property string $Kind
 * @property string $Title
 * @property float  $Quantity
 * @property float  $Amount
 */
class ShopInvoiceLine extends DataObject
{
    private static string $table_name = 'ShopInvoiceLine';

    private static array $db = [
        'Kind' => "Enum('Item,Modifier','Item')",
        'Title' => 'Varchar(255)',
        'Quantity' => 'Decimal(12,2)',
        'UnitAmount' => ShopCurrency::class,
        'Amount' => ShopCurrency::class,
        'Sort' => 'Int',
    ];

    private static array $has_one = [
        'Invoice' => ShopInvoice::class,
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
