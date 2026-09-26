<?php

namespace SilverShop\Invoicing;

use SilverStripe\Control\Email\Email;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Emails a document (invoice / credit memo) to the order's customer with the PDF attached, then records
 * the send time ({@link ShopInvoice::$EmailSent}) and logs a history comment. Never throws — a mail
 * failure returns false so it can't break invoice issuance.
 */
class DocumentEmailService
{
    public static function send($document, ?Member $actor = null): bool
    {
        $order = $document->Order();
        $recipient = ($order && $order->exists()) ? (string) $order->LatestEmail : '';
        if (!$recipient) {
            return false;
        }

        try {
            $pdf = PdfRenderer::pdf((string) $document->forTemplate());

            $email = Email::create();
            $email->setTo($recipient);
            $email->setSubject(self::subjectFor($document));
            $email->setHTMLTemplate('SilverShop/Invoicing/Email/DocumentEmail');
            $email->setData([
                'Document' => $document,
                'Order' => $order,
                'Label' => self::labelFor($document),
            ]);
            $email->addAttachmentFromData($pdf, $document->Number . '.pdf', 'application/pdf');
            $email->send();
        } catch (\Throwable $e) {
            return false;
        }

        $document->EmailSent = DBDatetime::now()->getValue();
        $document->write();
        $document->addComment('Emailed ' . self::labelFor($document) . ' to ' . $recipient, true, false, $actor);

        return true;
    }

    protected static function labelFor($document): string
    {
        return $document instanceof ShopCreditMemo ? 'credit memo' : 'invoice';
    }

    protected static function subjectFor($document): string
    {
        $config = SiteConfig::current_site_config();
        $seller = $config && $config->hasMethod('InvoiceSellerNameOrDefault')
            ? $config->InvoiceSellerNameOrDefault()
            : (string) ($config ? $config->Title : '');

        return trim($seller . ' ' . ucfirst(self::labelFor($document)) . ' ' . $document->Number);
    }
}
