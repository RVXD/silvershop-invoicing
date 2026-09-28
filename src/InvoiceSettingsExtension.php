<?php

namespace SilverShop\Invoicing;

use SilverShop\Model\TaxClass;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\LiteralField;
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
        'InvoiceAutoOnCapture' => 'Boolean(1)',
        'InvoiceEmailOnIssue' => 'Boolean',
        'InvoiceUseCustomerLocale' => 'Boolean',
    ];

    private static array $has_one = [
        'InvoiceLogo' => Image::class,
    ];

    private static array $owns = ['InvoiceLogo'];

    public function updateCMSFields(FieldList $fields): void
    {
        // Nest under SilverShop's central "Shop" settings tab (a sub-tab), falling back to a top-level
        // tab if SilverShop's ShopConfigExtension hasn't added the Shop tab.
        $tab = $fields->fieldByName('Root.Shop') ? 'Root.Shop.ShopTabs.Invoicing' : 'Root.Invoicing';

        $fields->addFieldsToTab($tab, [
            DropdownField::create('InvoicePaperSize', _t(self::class . '.PAPER_SIZE', 'PDF paper size'), [
                'A4' => 'A4',
                'Letter' => _t(self::class . '.PAPER_LETTER', 'Letter (US)'),
            ])
                ->setDescription(_t(self::class . '.PAPER_SIZE_DESC', 'A4 is the European standard; Letter is US.')),
            UploadField::create('InvoiceLogo', _t(self::class . '.LOGO', 'Logo'))
                ->setFolderName('invoicing')
                ->setAllowedFileCategories('image'),
            TextField::create('InvoiceSellerName', _t(self::class . '.SELLER_NAME', 'Seller name'))
                ->setDescription(_t(self::class . '.SELLER_NAME_DESC', 'Shown as the sender. Defaults to the site title when empty.')),
            TextareaField::create('InvoiceSellerAddress', _t(self::class . '.SELLER_ADDRESS', 'Seller address')),
            TextField::create('InvoiceVatNumber', _t(self::class . '.VAT_NUMBER', 'VAT number')),
            TextField::create('InvoiceRegistrationNumber', _t(self::class . '.REG_NUMBER', 'Registration / company no.')),
            TextareaField::create('InvoiceFooterText', _t(self::class . '.FOOTER_TEXT', 'Document footer text'))
                ->setDescription(_t(self::class . '.FOOTER_TEXT_DESC', 'Payment terms / thank-you note printed at the bottom of every document.')),
            CheckboxField::create('InvoiceAutoOnCapture', _t(self::class . '.AUTO_ON_CAPTURE', 'Automatically create and issue an invoice when a payment is captured'))
                ->setDescription(_t(self::class . '.AUTO_ON_CAPTURE_DESC', 'On: paid orders get a finalised invoice automatically. Off: staff create invoices manually (as drafts) from each order.')),
            CheckboxField::create('InvoiceEmailOnIssue', _t(self::class . '.EMAIL_ON_ISSUE', 'Email the invoice to the customer when it is issued'))
                ->setDescription(_t(self::class . '.EMAIL_ON_ISSUE_DESC', 'Automatically emails the PDF to the order email on capture. You can also email manually from each document.')),
            CheckboxField::create('InvoiceUseCustomerLocale', _t(self::class . '.USE_CUSTOMER_LOCALE', 'Issue documents in the customer\'s language'))
                ->setDescription(_t(self::class . '.USE_CUSTOMER_LOCALE_DESC', 'On: each invoice / credit memo is written and rendered in the order\'s locale (falling back to the shop language). Off: all documents use the shop language.')),
        ]);
        $fields->findOrMakeTab($tab)->setTitle(_t(self::class . '.TAB_INVOICING', 'Invoicing'));

        // Tax classes: a small editor under Shop settings. They rarely change, so this lives here rather
        // than in a top-level ModelAdmin. SilverShop core ships no admin for them at all.
        $taxTab = $fields->fieldByName('Root.Shop') ? 'Root.Shop.ShopTabs.Tax' : 'Root.Tax';
        $taxGrid = GridField::create('ShopTaxClasses', _t(self::class . '.TAX_CLASSES', 'Tax classes'), TaxClass::get(), GridFieldConfig_RecordEditor::create());
        $taxColumns = $taxGrid->getConfig()->getComponentByType(GridFieldDataColumns::class);
        if ($taxColumns) {
            // Rate as a percentage (not the raw fraction) + which class is the default for new products.
            $taxColumns->setDisplayFields([
                'Title' => _t(self::class . '.COL_TITLE', 'Title'),
                'RatePercent' => _t(self::class . '.COL_RATE', 'Rate'),
                'DefaultLabel' => _t(self::class . '.COL_DEFAULT', 'Default'),
            ]);
        }
        $fields->addFieldsToTab($taxTab, [
            $taxGrid,
            LiteralField::create(
                'TaxClassesNote',
                '<p class="message notice" style="display:flex;align-items:flex-start;gap:.5em">'
                . '<span class="font-icon-info-circled" aria-hidden="true"></span><span>'
                . _t(
                    self::class . '.TAX_CLASSES_NOTE',
                    'A tax class sets a VAT rate (e.g. 21% standard, 9% food). Assign one to a product on its '
                    . '<strong>Pricing</strong> tab; invoices and credit memos then show a VAT line per rate.'
                )
                . '</span></p>'
            ),
        ]);
        $fields->findOrMakeTab($taxTab)->setTitle(_t(self::class . '.TAB_TAX', 'Tax'));
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
