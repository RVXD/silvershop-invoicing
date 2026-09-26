<?php

namespace SilverShop\Invoicing;

use SilverStripe\Core\Extension;

/**
 * Applied to {@link \SilverStripe\Omnipay\Model\Payment}. When a payment is captured, issue an
 * immutable invoice for its order (idempotent per payment).
 */
class PaymentInvoiceExtension extends Extension
{
    public function onAfterWrite(): void
    {
        $payment = $this->getOwner();

        // Sites doing manual / partial invoicing can disable the automatic full invoice on capture:
        //   SilverShop\Invoicing\ShopInvoice:
        //     auto_invoice_on_capture: false
        if (!ShopInvoice::config()->get('auto_invoice_on_capture')) {
            return;
        }

        if ($payment->Status === 'Captured' && $payment->OrderID) {
            InvoiceService::invoiceForPayment($payment);
        }
    }
}
