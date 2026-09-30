<?php

namespace SilverShop\Invoicing;

use SilverShop\Model\Order;
use SilverShop\ORM\FieldType\ShopCurrency;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\ORM\DataObject;

/**
 * An immutable credit memo: the refund counterpart of a {@link ShopInvoice}. A numbered snapshot of
 * the order's items and adjustments issued when a payment is refunded. Optionally references the
 * original invoice. Never changed once issued.
 *
 * @property string $Number
 * @property float  $Total
 * @property ?string $Reason
 * @method   Order       Order()
 * @method   Payment     Payment()
 * @method   ShopInvoice Invoice()
 * @method   \SilverStripe\ORM\HasManyList<ShopCreditMemoLine> Lines()
 */
class ShopCreditMemo extends DataObject
{
    use NumberedImmutableDocument;

    private static string $table_name = 'SilverShop_CreditMemo';

    private static array $db = [
        'Status' => "Enum('Draft,Issued','Issued')",
        'Number' => 'Varchar(30)',
        'Issued' => 'Datetime',
        'Total' => ShopCurrency::class,
        'Currency' => 'Varchar(3)',
        'Reason' => 'Text',
        'CustomerNote' => 'Text',
        'EmailSent' => 'Datetime',
        'Locale' => 'Varchar(10)', // locale the document was issued/rendered in (frozen with its lines)
    ];

    // Credit memos are always issued directly (auto-created on refund).
    private static array $defaults = ['Status' => 'Issued'];

    private static array $has_one = [
        'Order' => Order::class,
        'Payment' => Payment::class,
        'Invoice' => ShopInvoice::class,
    ];

    private static array $has_many = [
        'Lines' => ShopCreditMemoLine::class,
        'Comments' => ShopDocumentComment::class . '.Document',
    ];

    private static array $cascade_deletes = ['Lines'];

    private static string $default_sort = '"Number" ASC';

    private static array $summary_fields = [
        'Number' => 'Credit Memo',
        'Issued.Nice' => 'Issued',
        'Order.Reference' => 'Order',
        'Order.Name' => 'Customer',
        'Total.Nice' => 'Total',
        'EmailSent.Nice' => 'Emailed',
    ];

    private static array $searchable_fields = ['Number', 'Order.Reference', 'Order.LatestEmail'];

    public function summaryFields(): array
    {
        return [
            'Number' => _t(self::class . '.col_Number', 'Credit Memo'),
            'Issued.Nice' => _t(self::class . '.col_Issued', 'Issued'),
            'Order.Reference' => _t(self::class . '.col_Order', 'Order'),
            'Order.Name' => _t(self::class . '.col_Customer', 'Customer'),
            'Total.Nice' => _t(self::class . '.col_Total', 'Total'),
            'EmailSent.Nice' => _t(self::class . '.col_Emailed', 'Emailed'),
        ];
    }

    private static string $number_prefix = 'CM-';

    private static string $document_url_segment = 'creditmemo';

    public function forTemplate(): string
    {
        return $this->renderInDocumentLocale('SilverShop/Invoicing/CreditMemo');
    }
}
