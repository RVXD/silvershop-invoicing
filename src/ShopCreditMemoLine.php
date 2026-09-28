<?php

namespace SilverShop\Invoicing;

use SilverShop\Model\Modifiers\OrderModifier;
use SilverShop\Model\OrderItem;
use SilverShop\ORM\FieldType\ShopCurrency;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;

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
        'Kind' => "Enum('Item,Modifier,Tax','Item')",
        'Title' => 'Varchar(255)',
        'SKU' => 'Varchar(100)',
        'Quantity' => 'Decimal(12,2)',
        'UnitAmount' => ShopCurrency::class,
        'Amount' => ShopCurrency::class,
        'TaxRate' => 'Decimal(6,4)', // VAT rate of the source product (0.09 / 0.21); 0 for non-item lines
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
        'NiceAmount' => 'Amount',
    ];

    private static array $casting = [
        'NiceAmount' => 'HTMLText',
    ];

    public function summaryFields(): array
    {
        return [
            'Title' => _t(self::class . '.col_Description', 'Description'),
            'Quantity' => _t(self::class . '.col_Qty', 'Qty'),
            'NiceAmount' => _t(self::class . '.col_Amount', 'Amount'),
        ];
    }

    /**
     * Amount via ShopCurrency::Nice(), which wraps negatives in <span class="negative">. Returned as
     * HTMLText so the CMS GridField renders the markup instead of escaping it to visible raw tags.
     */
    public function getNiceAmount(): DBHTMLText
    {
        return DBField::create_field('HTMLText', $this->dbObject('Amount')->Nice());
    }
}
