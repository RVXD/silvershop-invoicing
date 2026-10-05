<?php

namespace SilverShop\Invoicing;

use SilverShop\Model\Order;
use SilverShop\ORM\FieldType\ShopCurrency;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

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

    private static string $table_name = 'SilverShop_Invoice';

    private static array $db = [
        'Status' => "Enum('Draft,Issued','Issued')",
        'Number' => 'Varchar(30)',
        'Issued' => 'Datetime',
        'Total' => ShopCurrency::class,
        'Currency' => 'Varchar(3)',
        'CustomerNote' => 'Text',
        'EmailSent' => 'Datetime',
        'Locale' => 'Varchar(10)', // locale the document was issued/rendered in (frozen with its lines)
    ];

    // New invoices start as an editable draft (CMS "Create invoice"); the auto/service paths set Issued.
    private static array $defaults = ['Status' => 'Draft'];

    private static array $has_one = [
        'Order' => Order::class,
        'Payment' => Payment::class,
    ];

    private static array $has_many = [
        'Lines' => ShopInvoiceLine::class,
        'Comments' => ShopDocumentComment::class . '.Document',
    ];

    private static array $cascade_deletes = ['Lines'];

    private static string $default_sort = '"Number" ASC';

    private static array $summary_fields = [
        'Number' => 'Invoice',
        'Status' => 'Status',
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
            'Number' => _t(self::class . '.col_Number', 'Invoice'),
            'Status' => _t(self::class . '.col_Status', 'Status'),
            'Issued.Nice' => _t(self::class . '.col_Issued', 'Issued'),
            'Order.Reference' => _t(self::class . '.col_Order', 'Order'),
            'Order.Name' => _t(self::class . '.col_Customer', 'Customer'),
            'Total.Nice' => _t(self::class . '.col_Total', 'Total'),
            'EmailSent.Nice' => _t(self::class . '.col_Emailed', 'Emailed'),
        ];
    }

    /**
     * Prefix for generated invoice numbers, e.g. INV-00001.
     */
    private static string $number_prefix = 'INV-';

    private static string $document_url_segment = 'invoice';

    /**
     * Automatically issue a full invoice when a payment is captured. Set false for manual / partial
     * invoicing (issue invoices yourself via InvoiceService::invoiceForItems()).
     */
    private static bool $auto_invoice_on_capture = true;

    /**
     * Set by InvoiceService (which snapshots its own lines) to suppress the auto-snapshot below. A draft
     * created through the CMS "Create invoice" add form has no lines yet, so it snapshots on first save.
     */
    public bool $skipLineSnapshot = false;

    protected function onAfterWrite(): void
    {
        parent::onAfterWrite();

        // First save of a CMS-created draft: snapshot the order's still-to-invoice lines into it.
        if ($this->skipLineSnapshot || $this->isIssued() || !$this->OrderID || $this->Lines()->exists()) {
            return;
        }

        $order = $this->Order();
        if (!$order || !$order->exists()) {
            return;
        }

        // Carry the order currency + resolved locale onto the draft (raw update — avoids re-entering
        // onAfterWrite). The locale freezes the language its snapshotted lines are written in.
        $locale = self::resolveDocumentLocale($order);
        if (!$this->Currency || !$this->Locale) {
            $this->Currency = $this->Currency ?: $order->Currency();
            $this->Locale = $this->Locale ?: $locale;
            DB::prepared_query(
                'UPDATE "SilverShop_Invoice" SET "Currency" = ?, "Locale" = ? WHERE "ID" = ?',
                [(string) $this->Currency, (string) $this->Locale, $this->ID]
            );
        }

        $remaining = InvoiceService::remainingQuantities($order);
        if (!$remaining) {
            return;
        }

        self::withLocale((string) $this->Locale, function () use ($order, $remaining) {
            $sort = DocumentLineSnapshotter::snapshotItems($order, ShopInvoiceLine::class, 'InvoiceID', $this->ID, $remaining);
            $sort = DocumentLineSnapshotter::snapshotModifiers($order, ShopInvoiceLine::class, 'InvoiceID', $this->ID, $sort);
            DocumentLineSnapshotter::snapshotTaxSummary(ShopInvoiceLine::class, 'InvoiceID', $this->ID, $sort, $order);
        });
    }

    /**
     * Render the invoice through the theme cascade so a project can override
     * templates/SilverShop/Invoicing/Invoice.ss in app/ or its theme.
     */
    public function forTemplate(): string
    {
        return $this->renderInDocumentLocale('SilverShop/Invoicing/Invoice');
    }

    /**
     * An issued invoice that has not already been credited can be cancelled (via a reversing credit memo).
     */
    public function canBeCancelled(): bool
    {
        return $this->isIssued() && !ShopCreditMemo::get()->filter('InvoiceID', $this->ID)->exists();
    }

    /**
     * cms-actions handler for the "Cancel invoice" button: issues a reversing credit memo.
     */
    public function doCancelInvoice($data = null, $form = null, $controller = null): string
    {
        $memo = CreditMemoService::creditMemoForInvoice($this);
        if (!$memo) {
            return _t(self::class . '.CANCEL_FAIL', 'This invoice cannot be cancelled (already credited, or not issued).');
        }

        return _t(self::class . '.CANCEL_OK', 'Issued credit memo {number} cancelling this invoice.', ['number' => $memo->Number]);
    }
}
