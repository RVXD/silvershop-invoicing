<?php

namespace SilverShop\Invoicing\Tests;

use SilverShop\Invoicing\CreditMemoService;
use SilverShop\Invoicing\ShopCreditMemo;
use SilverShop\Model\Order;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Omnipay\Model\Payment;

/**
 * Covers credit-memo issuance on refund: snapshot + reconciliation, CM- numbering, idempotency and
 * immutability. Mirrors ShopInvoiceTest.
 */
class ShopCreditMemoTest extends SapphireTest
{
    protected static $fixture_file = 'ShopCreditMemoTest.yml';

    protected static bool $use_draft_site = true;

    public function testCreditMemoSnapshotsAndReconciles(): void
    {
        $order = $this->objFromFixture(Order::class, 'order');
        $memo = CreditMemoService::creditMemoForOrder($order);

        $this->assertStringStartsWith('CM-', $memo->Number);
        $this->assertEquals((float) $order->Total(), (float) $memo->Total);

        $sum = 0.0;
        foreach ($memo->Lines() as $line) {
            $sum += (float) $line->Amount;
        }
        $this->assertEquals((float) $memo->Total, $sum, 'lines reconcile to the credit-memo total');
    }

    public function testCreditMemoIssuedOnRefundIsIdempotent(): void
    {
        $order = $this->objFromFixture(Order::class, 'order');

        $payment = Payment::create();
        $payment->OrderID = $order->ID;
        $payment->Gateway = 'Manual';
        $payment->MoneyAmount = 7;
        $payment->MoneyCurrency = 'NZD';
        $payment->Status = 'Refunded';
        $payment->write(); // PaymentCreditMemoExtension issues the credit memo

        CreditMemoService::creditMemoForPayment($payment); // second call must not duplicate

        $memos = ShopCreditMemo::get()->filter('PaymentID', $payment->ID);
        $this->assertCount(1, $memos);
        $this->assertEquals(7.0, (float) $memos->first()->Total);
    }

    public function testCreditMemoIsImmutable(): void
    {
        $order = $this->objFromFixture(Order::class, 'order');
        $memo = CreditMemoService::creditMemoForOrder($order);

        $this->assertFalse($memo->canEdit());
        $this->assertFalse($memo->canDelete());

        $memo->Total = 999;
        $this->expectException(ValidationException::class);
        $memo->write();
    }
}
