<?php

namespace SilverShop\Invoicing\Tests;

use SilverShop\Invoicing\InvoiceService;
use SilverShop\Invoicing\LedgerDocumentLink;
use SilverShop\Invoicing\ShopInvoice;
use SilverShop\Ledger\ShopPostingEntry;
use SilverShop\Model\Order;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Omnipay\Model\Payment;

/**
 * Covers the invoice ↔ ledger link: when silvershop/ledger is installed, each posting entry references
 * its source document (invoice / credit memo). Exercises both directions — document issued after the
 * entry exists (forward) and entry written after the document exists (reverse) — plus the reverse
 * lookup. Skipped when the ledger is not present. Issuance is driven explicitly, so the test does not
 * depend on the merchant's auto-invoice-on-capture SiteConfig switch (off by default).
 */
class LedgerDocumentLinkTest extends SapphireTest
{
    protected static $fixture_file = 'ShopInvoiceTest.yml';

    protected static bool $use_draft_site = true;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(ShopPostingEntry::class)) {
            $this->markTestSkipped('silvershop/ledger is not installed');
        }
    }

    private function payment(Order $order, string $status): Payment
    {
        $payment = Payment::create();
        $payment->OrderID = $order->ID;
        $payment->Gateway = 'Manual';
        $payment->MoneyAmount = 7;
        $payment->MoneyCurrency = 'NZD';
        $payment->Status = $status;
        $payment->write();

        return $payment;
    }

    private function capturedEntryFor(Payment $payment): ?ShopPostingEntry
    {
        return ShopPostingEntry::get()->filter(['PaymentID' => $payment->ID, 'Type' => 'Captured'])->first();
    }

    public function testForwardLinkWhenDocumentIssuedAfterEntry(): void
    {
        $order = $this->objFromFixture(Order::class, 'order');
        $payment = $this->payment($order, 'Captured'); // posts the Captured ledger entry

        $this->assertNotNull($this->capturedEntryFor($payment), 'a Captured ledger entry is posted for the payment');

        // Idempotent: issues the invoice (and links the entry) unless the merchant's auto-on-capture switch
        // already did on write. Either way the entry ends up referencing the invoice.
        $invoice = InvoiceService::invoiceForPayment($payment);
        $this->assertNotNull($invoice, 'an invoice is issued for the payment');

        $entry = $this->capturedEntryFor($payment);
        $this->assertSame((int) $invoice->ID, (int) $entry->DocumentID, 'the ledger entry references the invoice');
        $this->assertSame(ShopInvoice::class, $entry->DocumentClass);
        $this->assertSame((string) $invoice->Number, $entry->getDocumentLabel());
    }

    public function testReverseLinkWhenEntryWrittenAfterDocument(): void
    {
        $order = $this->objFromFixture(Order::class, 'order');
        $payment = $this->payment($order, 'Created'); // not captured → no ledger entry yet

        $invoice = InvoiceService::invoiceForPayment($payment);
        $this->assertNotNull($invoice);
        $this->assertNull($this->capturedEntryFor($payment), 'no ledger entry before capture');

        // Post the ledger entry now; PostingEntryDocumentExtension links it to the existing invoice on write.
        $entry = ShopPostingEntry::create();
        $entry->Type = 'Captured';
        $entry->Amount = 7;
        $entry->OrderID = $order->ID;
        $entry->PaymentID = $payment->ID;
        $entry->write();

        $this->assertSame((int) $invoice->ID, (int) $entry->DocumentID, 'the new entry links to the existing invoice');
        $this->assertSame(ShopInvoice::class, $entry->DocumentClass);
    }

    public function testDocumentForResolvesTheSourceDocument(): void
    {
        $order = $this->objFromFixture(Order::class, 'order');
        $payment = $this->payment($order, 'Captured');
        $invoice = InvoiceService::invoiceForPayment($payment);

        $document = LedgerDocumentLink::documentFor($this->capturedEntryFor($payment));
        $this->assertInstanceOf(ShopInvoice::class, $document);
        $this->assertSame((int) $invoice->ID, (int) $document->ID);
    }
}
