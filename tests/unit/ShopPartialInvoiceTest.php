<?php

namespace SilverShop\Invoicing\Tests;

use SilverShop\Invoicing\CreditMemoService;
use SilverShop\Invoicing\InvoiceService;
use SilverShop\Invoicing\ShopInvoice;
use SilverShop\Model\Modifiers\OrderModifier;
use SilverShop\Model\Modifiers\Tax\FlatTax;
use SilverShop\Model\Order;
use SilverShop\Page\Product;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;

/**
 * A modifier that hides itself from the order table (as a zero-value discount does via
 * OrderDiscountModifier::ShowInTable() = Amount > 0). Used to prove such lines aren't snapshotted.
 */
class HiddenTestModifier extends OrderModifier implements TestOnly
{
    private static string $table_name = 'HiddenTestModifier';

    public function ShowInTable(): bool
    {
        return false;
    }
}

/**
 * Covers partial / multiple invoicing: invoicing a subset of item quantities, quantity tracking across
 * invoices, and modifiers landing on the completing invoice so the sum reconciles to the order.
 */
class ShopPartialInvoiceTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [HiddenTestModifier::class];

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

    public function testHiddenModifierIsNotSnapshotted(): void
    {
        $product = Product::create();
        $product->Title = 'Widget';
        $product->BasePrice = 10;
        $product->write();
        $product->publishRecursive();

        $order = Order::create();
        $order->write();
        $item = $product->createItem(1);
        $item->write();
        $order->Items()->add($item);

        // A modifier the order itself would hide (e.g. a zero-value discount) must not become a line.
        $hidden = HiddenTestModifier::create();
        $hidden->OrderID = $order->ID;
        $hidden->Amount = 0;
        $hidden->Type = 'Deductable';
        $hidden->write();

        // A real charge still lands on the invoice.
        $charge = OrderModifier::create();
        $charge->OrderID = $order->ID;
        $charge->Amount = 4;
        $charge->Type = 'Chargable';
        $charge->write();

        // Full (completing) invoice → modifiers snapshotted; the hidden zero-value one must be skipped.
        $invoice = InvoiceService::invoiceForItems($order, [$item->ID => 1]);
        $this->assertCount(2, $invoice->Lines()); // item + the $4 charge only, no hidden discount line
        $this->assertSame(0, (int) $invoice->Lines()->filter('OrderModifierID', $hidden->ID)->count());
        $this->assertEqualsWithDelta(14, (float) $invoice->Total, 0.001);
    }

    public function testInclusiveTaxCapturedAsMemoLineExcludedFromTotal(): void
    {
        // VAT-inclusive: prices already contain the tax, so the modifier is Type 'Ignored' and must not
        // change the total — but a compliant invoice still states it, as an informational Tax line.
        Config::modify()->set(FlatTax::class, 'rate', 0.21);
        Config::modify()->set(FlatTax::class, 'exclusive', false);
        Config::modify()->merge(Order::class, 'modifiers', [FlatTax::class]);

        $product = Product::create();
        $product->Title = 'Widget';
        $product->BasePrice = 100;
        $product->write();
        $product->publishRecursive();

        $order = Order::create();
        $order->write();
        $item = $product->createItem(1);
        $item->write();
        $order->Items()->add($item);
        $order->calculate();
        $order->write();

        $invoice = InvoiceService::invoiceForOrder($order);

        // The tax is snapshotted as a Tax-kind line with a positive amount…
        $taxLines = $invoice->Lines()->filter('Kind', 'Tax');
        $this->assertCount(1, $taxLines);
        $this->assertGreaterThan(0, (float) $taxLines->first()->Amount);
        $this->assertSame(1, (int) $invoice->TaxLines()->count());

        // …but it is excluded from the total: the gross stays 100 and the non-tax lines already sum to it.
        $this->assertEqualsWithDelta(100, (float) $invoice->Total, 0.01);
        $nonTax = (float) $invoice->Lines()->exclude('Kind', 'Tax')->sum('Amount');
        $this->assertEqualsWithDelta((float) $invoice->Total, $nonTax, 0.01);
    }
}
