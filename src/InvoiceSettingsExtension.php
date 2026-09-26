<?php

namespace SilverShop\Invoicing;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;

/**
 * Central invoice settings, stored on SiteConfig: seller identity (logo, name, address, VAT /
 * registration numbers), a document footer, and the PDF paper size (A4 default for EU, or US Letter).
 * Surfaced on every invoice / credit memo document via `$SiteConfig.*` template calls.
 *
 * @property string $InvoiceSellerName
 * @property string $InvoiceSellerAddress
 * @property string $InvoiceVatNumber
 * @property string $InvoiceRegistrationNumber
 * @property string $InvoiceFooterText
 * @property string $InvoicePaperSize
 */
class InvoiceSettingsExtension extends Extension
{
    private static array $db = [
        'InvoiceSellerName' => 'Varchar(255)',
        'InvoiceSellerAddress' => 'Text',
        'InvoiceVatNumber' => 'Varchar(64)',
        'InvoiceRegistrationNumber' => 'Varchar(64)',
        'InvoiceFooterText' => 'Text',
        'InvoicePaperSize' => "Enum('A4,Letter','A4')",
        'InvoiceEmailOnIssue' => 'Boolean',
    ];

    private static array $has_one = [
        'InvoiceLogo' => Image::class,
    ];

    private static array $owns = ['InvoiceLogo'];

    public function updateCMSFields(FieldList $fields): void
    {
        $fields->addFieldsToTab('Root.Invoicing', [
            DropdownField::create('InvoicePaperSize', 'PDF paper size', ['A4' => 'A4', 'Letter' => 'Letter (US)'])
                ->setDescription('A4 is the European standard; Letter is US.'),
            UploadField::create('InvoiceLogo', 'Logo')
                ->setFolderName('invoicing')
                ->setAllowedFileCategories('image'),
            TextField::create('InvoiceSellerName', 'Seller name')
                ->setDescription('Shown as the sender. Defaults to the site title when empty.'),
            TextareaField::create('InvoiceSellerAddress', 'Seller address'),
            TextField::create('InvoiceVatNumber', 'VAT number'),
            TextField::create('InvoiceRegistrationNumber', 'Registration / company no.'),
            TextareaField::create('InvoiceFooterText', 'Document footer text')
                ->setDescription('Payment terms / thank-you note printed at the bottom of every document.'),
            CheckboxField::create('InvoiceEmailOnIssue', 'Email the invoice to the customer when it is issued')
                ->setDescription('Automatically emails the PDF to the order email on capture. You can also email manually from each document.'),
        ]);
    }

    /**
     * Seller name, falling back to the site title.
     */
    public function InvoiceSellerNameOrDefault(): string
    {
        return (string) ($this->getOwner()->InvoiceSellerName ?: $this->getOwner()->Title);
    }

    /**
     * PDF paper size, guaranteed to be a supported value (defaults to A4).
     */
    public function InvoicePaperSizeOrDefault(): string
    {
        $size = (string) $this->getOwner()->InvoicePaperSize;

        return in_array($size, ['A4', 'Letter'], true) ? $size : 'A4';
    }

    /**
     * The logo as an inline data-URI <img> (scaled down), so it renders in both the browser and in
     * dompdf (which has remote fetching disabled). Empty when no logo is set.
     */
    public function InvoiceLogoImageTag(): DBHTMLText
    {
        $empty = DBField::create_field('HTMLFragment', '');
        $logo = $this->getOwner()->InvoiceLogo();
        if (!$logo || !$logo->exists()) {
            return $empty;
        }

        try {
            $scaled = $logo->ScaleMaxWidth(260) ?: $logo;
            $data = $scaled->getString();
            if (!$data) {
                return $empty;
            }

            return DBField::create_field('HTMLFragment', sprintf(
                '<img class="brand__logo" src="data:%s;base64,%s" alt="%s" />',
                $scaled->getMimeType(),
                base64_encode($data),
                Convert::raw2att($this->InvoiceSellerNameOrDefault())
            ));
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    public function InvoiceSellerAddressHTML(): DBHTMLText
    {
        return $this->multilineToHtml((string) $this->getOwner()->InvoiceSellerAddress);
    }

    public function InvoiceFooterTextHTML(): DBHTMLText
    {
        return $this->multilineToHtml((string) $this->getOwner()->InvoiceFooterText);
    }

    private function multilineToHtml(string $text): DBHTMLText
    {
        $safe = nl2br(Convert::raw2xml(trim($text)), false);

        return DBField::create_field('HTMLFragment', $safe);
    }
}
