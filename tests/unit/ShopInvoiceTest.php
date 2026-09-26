<?php

namespace SilverShop\Invoicing\Tests;

use SilverShop\Invoicing\InvoiceService;
use SilverShop\Invoicing\ShopInvoice;
use SilverShop\Model\Order;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Omnipay\Model\Payment;

/**
 * Covers invoice issuance from an order/payment: line snapshotting (items + signed modifiers),
 * reconciliation to the order total, sequential numbering, idempotency and immutability.
 */
class ShopInvoiceTest extends SapphireTest
{
    protected static $fixture_file = 'ShopInvoiceTest.yml';

    protected static bool $use_draft_site = true;

    public function testInvoiceSnapshotsModifiersAndReconciles(): void
    {
        $order = $this->objFromFixture(Order::class, 'order');
        $invoice = InvoiceService::invoiceForOrder($order);

        $this->assertNotEmpty($invoice->Number, 'invoice is numbered');
        $this->assertEquals((float) $order->Total(), (float) $invoice->Total);

        // No item lines here (empty order); two modifier lines, signed by Type.
        $amounts = array_map('floatval', $invoice->Lines()->filter('Kind', 'Modifier')->column('Amount'));
        sort($amounts);
        $this->assertSame([-3.0, 10.0], $amounts, 'Chargable +10, Deductable -3');

        $sum = 0.0;
        foreach ($invoice->Lines() as $line) {
            $sum += (float) $line->Amount;
        }
        $this->assertEquals((float) $invoice->Total, $sum, 'lines reconcile to the invoice total');
    }

    public function testInvoiceIssuedOnCaptureIsIdempotent(): void
    {
        $order = $this->objFromFixture(Order::class, 'order');

        $payment = Payment::create();
        $payment->OrderID = $order->ID;
        $payment->Gateway = 'Manual';
        $payment->MoneyAmount = 7;
        $payment->MoneyCurrency = 'NZD';
        $payment->Status = 'Captured';
        $payment->write(); // PaymentInvoiceExtension issues the invoice

        InvoiceService::invoiceForPayment($payment); // second call must not duplicate

        $this->assertCount(1, ShopInvoice::get()->filter('PaymentID', $payment->ID));
    }

    public function testNumbersAreSequential(): void
    {
        $order = $this->objFromFixture(Order::class, 'order');
        $a = InvoiceService::invoiceForOrder($order);
        $b = InvoiceService::invoiceForOrder($order);

        $this->assertNotEquals($a->Number, $b->Number);
    }

    public function testPerYearGapFreeNumbering(): void
    {
        $order = $this->objFromFixture(Order::class, 'order');
        $year = date('Y');

        $a = InvoiceService::invoiceForOrder($order);
        $b = InvoiceService::invoiceForOrder($order);
        $c = InvoiceService::invoiceForOrder($order);

        // Format: INV-<year>-<zero-padded sequence>.
        $this->assertMatchesRegularExpression('/^INV-' . $year . '-\d{5}$/', $a->Number);

        // Gap-free: the trailing sequence increments by exactly 1 each time.
        $seq = fn ($number) => (int) substr((string) $number, strrpos((string) $number, '-') + 1);
        $this->assertSame($seq($a->Number) + 1, $seq($b->Number));
        $this->assertSame($seq($b->Number) + 1, $seq($c->Number));
    }

    public function testInvoiceIsImmutable(): void
    {
        $order = $this->objFromFixture(Order::class, 'order');
        $invoice = InvoiceService::invoiceForOrder($order);

        $this->assertFalse($invoice->canEdit());
        $this->assertFalse($invoice->canDelete());

        $invoice->Total = 999;
        $this->expectException(ValidationException::class);
        $invoice->write();
    }
}
