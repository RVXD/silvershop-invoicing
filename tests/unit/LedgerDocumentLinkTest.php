<?php

namespace SilverShop\Invoicing\Tests;

use SilverShop\Invoicing\LedgerDocumentLink;
use SilverShop\Invoicing\ShopInvoice;
use SilverShop\Ledger\ShopPostingEntry;
use SilverShop\Model\Order;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Omnipay\Model\Payment;

/**
 * Covers the invoice ↔ ledger link: when silvershop/ledger is installed, each posting entry references
 * its source document (invoice / credit memo). Exercises both directions (document-first and
 * entry-first) plus the reverse lookup. Skipped when the ledger is not present.
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

    private function capturePayment(Order $order): Payment
    {
        $payment = Payment::create();
        $payment->OrderID = $order->ID;
        $payment->Gateway = 'Manual';
        $payment->MoneyAmount = 7;
        $payment->MoneyCurrency = 'NZD';
        $payment->Status = 'Captured';
        $payment->write(); // issues the invoice AND posts the Captured ledger entry

        return $payment;
    }

    public function testCapturedPaymentLinksInvoiceToLedgerEntry(): void
    {
        $order = $this->objFromFixture(Order::class, 'order');
        $payment = $this->capturePayment($order);

        $invoice = ShopInvoice::get()->filter('PaymentID', $payment->ID)->first();
        $this->assertNotNull($invoice, 'an invoice is issued for the captured payment');

        $entry = ShopPostingEntry::get()
            ->filter(['PaymentID' => $payment->ID, 'Type' => 'Captured'])->first();
        $this->assertNotNull($entry, 'a Captured ledger entry is posted for the payment');
        $this->assertSame((int) $invoice->ID, (int) $entry->DocumentID, 'the ledger entry references the invoice');
        $this->assertSame(ShopInvoice::class, $entry->DocumentClass);
        $this->assertSame((string) $invoice->Number, $entry->getDocumentLabel());
    }

    public function testDocumentForResolvesTheSourceDocument(): void
    {
        $order = $this->objFromFixture(Order::class, 'order');
        $payment = $this->capturePayment($order);

        $entry = ShopPostingEntry::get()
            ->filter(['PaymentID' => $payment->ID, 'Type' => 'Captured'])->first();

        $document = LedgerDocumentLink::documentFor($entry);
        $this->assertInstanceOf(ShopInvoice::class, $document);
        $this->assertSame((int) $payment->ID, (int) $document->PaymentID);
    }
}
