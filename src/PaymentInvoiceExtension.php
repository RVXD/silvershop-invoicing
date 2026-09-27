<?php

namespace SilverShop\Invoicing;

use SilverStripe\Core\Extension;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Applied to {@link \SilverStripe\Omnipay\Model\Payment}. When a payment is captured, issue an
 * immutable invoice for its order (idempotent per payment).
 */
class PaymentInvoiceExtension extends Extension
{
    public function onAfterWrite(): void
    {
        $payment = $this->getOwner();

        // Both must allow it: the developer master switch (ShopInvoice.auto_invoice_on_capture, YAML) and
        // the merchant setting (SiteConfig → Shop → Invoicing → "Automatically create ... on capture").
        if (!ShopInvoice::config()->get('auto_invoice_on_capture')) {
            return;
        }
        if (!SiteConfig::current_site_config()->InvoiceAutoOnCapture) {
            return;
        }

        if ($payment->Status === 'Captured' && $payment->OrderID) {
            InvoiceService::invoiceForPayment($payment);
        }
    }
}
