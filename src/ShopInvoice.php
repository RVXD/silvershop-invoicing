<?php

namespace SilverShop\Invoicing;

use SilverShop\Model\Order;
use SilverShop\ORM\FieldType\ShopCurrency;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\ORM\DataObject;

/**
 * An immutable financial document issued against an order: a numbered snapshot of the order's items
 * and adjustments (discount / tax / shipping) at the moment of capture. Never changed once issued.
 *
 * @property string $Number
 * @property float  $Total
 * @property ?string $Currency
 * @method   Order   Order()
 * @method   Payment Payment()
 * @method   \SilverStripe\ORM\HasManyList<ShopInvoiceLine> Lines()
 */
class ShopInvoice extends DataObject
{
    use NumberedImmutableDocument;

    private static string $table_name = 'ShopInvoice';

    private static array $db = [
        'Number' => 'Varchar(30)',
        'Issued' => 'Datetime',
        'Total' => ShopCurrency::class,
        'Currency' => 'Varchar(3)',
    ];

    private static array $has_one = [
        'Order' => Order::class,
        'Payment' => Payment::class,
    ];

    private static array $has_many = [
        'Lines' => ShopInvoiceLine::class,
    ];

    private static array $cascade_deletes = ['Lines'];

    private static string $default_sort = '"Number" ASC';

    private static array $summary_fields = [
        'Number' => 'Invoice',
        'Issued.Nice' => 'Issued',
        'Order.Reference' => 'Order',
        'Order.Name' => 'Customer',
        'Total.Nice' => 'Total',
    ];

    private static array $searchable_fields = ['Number', 'Order.Reference', 'Order.LatestEmail'];

    /**
     * Prefix for generated invoice numbers, e.g. INV-00001.
     */
    private static string $number_prefix = 'INV-';

    /**
     * Render the invoice through the theme cascade so a project can override
     * templates/SilverShop/Invoicing/Invoice.ss in app/ or its theme.
     */
    public function forTemplate(): string
    {
        return (string) $this->renderWith('SilverShop/Invoicing/Invoice');
    }
}
