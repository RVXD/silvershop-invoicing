<?php

namespace SilverShop\Invoicing\Tests;

use SilverShop\Invoicing\CreditMemoService;
use SilverShop\Invoicing\InvoiceService;
use SilverShop\Invoicing\ShopInvoice;
use SilverShop\Model\Modifiers\OrderModifier;
use SilverShop\Model\Order;
use SilverShop\Page\Product;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Dev\SapphireTest;

/**
 * Covers partial / multiple invoicing: invoicing a subset of item quantities, quantity tracking across
 * invoices, and modifiers landing on the completing invoice so the sum reconciles to the order.
 */
class ShopPartialInvoiceTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testPartialThenCompletingInvoice(): void
    {
        $product = Product::create();
        $product->Title = 'Widget';
        $product->BasePrice = 10;
        $product->write();
        $product->publishRecursive();

        $order = Order::create();
        $order->write();

        $item = $product->createItem(5);
        $item->write();
        $order->Items()->add($item);

        // A $4 shipping-style charge — should appear only on the completing invoice.
        $ship = OrderModifier::create();
        $ship->OrderID = $order->ID;
        $ship->Amount = 4;
        $ship->Type = 'Chargable';
        $ship->write();

        $itemID = $item->ID;

        $this->assertEqualsWithDelta(5, InvoiceService::remainingQuantities($order)[$itemID], 0.001);

        // Partial: invoice 2 of 5. Not completing → item line only, no modifier. Total = 2 × 10.
        $a = InvoiceService::invoiceForItems($order, [$itemID => 2]);
        $this->assertNotNull($a);
        $this->assertEqualsWithDelta(20, (float) $a->Total, 0.001);
        $this->assertCount(1, $a->Lines());
        $this->assertEqualsWithDelta(3, InvoiceService::remainingQuantities($order)[$itemID], 0.001);
        $this->assertFalse(InvoiceService::isFullyInvoiced($order));

        // Complete: invoice the remaining 3. Completing → modifier included. Total = 3 × 10 + 4.
        $b = InvoiceService::invoiceForItems($order, [$itemID => 3]);
        $this->assertNotNull($b);
        $this->assertEqualsWithDelta(34, (float) $b->Total, 0.001);
        $this->assertCount(2, $b->Lines()); // item line + modifier line
        $this->assertTrue(InvoiceService::isFullyInvoiced($order));

        // Nothing left → over-invoicing returns null.
        $this->assertNull(InvoiceService::invoiceForItems($order, [$itemID => 1]));

        // Σ invoices reconciles: 20 + 34 = 54 = 5 × 10 + 4.
        $this->assertEqualsWithDelta(54, (float) $a->Total + (float) $b->Total, 0.001);
    }

    public function testDraftLifecycleAndLineEditing(): void
    {
        $this->logInWithPermission('CMS_ACCESS');

        $product = Product::create();
        $product->Title = 'Widget';
        $product->BasePrice = 10;
        $product->write();
        $product->publishRecursive();

        $order = Order::create();
        $order->write();
        $item = $product->createItem(4);
        $item->write();
        $order->Items()->add($item);

        // Manual invoicing → an editable draft with no number.
        $draft = InvoiceService::invoiceForItems($order, [$item->ID => 4]);
        $this->assertNotNull($draft);
        $this->assertFalse($draft->isIssued());
        $this->assertEmpty($draft->Number);
        $this->assertTrue($draft->canEdit());
        $this->assertEqualsWithDelta(40, (float) $draft->Total, 0.001);

        // Editing a draft line quantity recomputes the invoice total.
        $line = $draft->ItemLines()->first();
        $line->Quantity = 3;
        $line->write();
        $draft = ShopInvoice::get()->byID($draft->ID);
        $this->assertEqualsWithDelta(30, (float) $draft->Total, 0.001);

        // Issue → gap-free number assigned, then frozen.
        $draft->issue();
        $this->assertTrue($draft->isIssued());
        $this->assertNotEmpty($draft->Number);
        $this->assertFalse($draft->canEdit());

        $draft->Total = 999;
        $this->expectException(ValidationException::class);
        $draft->write();
    }

    public function testCancelInvoiceIssuesReversingCreditMemo(): void
    {
        $product = Product::create();
        $product->Title = 'Widget';
        $product->BasePrice = 10;
        $product->write();
        $product->publishRecursive();

        $order = Order::create();
        $order->write();
        $item = $product->createItem(2);
        $item->write();
        $order->Items()->add($item);

        $invoice = InvoiceService::invoiceForOrder($order); // issued, total 20
        $this->assertTrue($invoice->isIssued());
        $this->assertTrue($invoice->canBeCancelled());

        $memo = CreditMemoService::creditMemoForInvoice($invoice);
        $this->assertNotNull($memo);
        $this->assertTrue($memo->isIssued());
        $this->assertNotEmpty($memo->Number);
        $this->assertEqualsWithDelta((float) $invoice->Total, (float) $memo->Total, 0.001);
        $this->assertSame((int) $invoice->ID, (int) $memo->InvoiceID);
        $this->assertSame($invoice->ItemLines()->count(), $memo->ItemLines()->count());

        // Idempotent: an already-credited invoice cannot be cancelled again.
        $this->assertFalse($invoice->canBeCancelled());
        $this->assertNull(CreditMemoService::creditMemoForInvoice($invoice));
    }
}
