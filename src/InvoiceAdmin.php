<?php

namespace SilverShop\Invoicing;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridField;

/**
 * Global, read-only view of every issued invoice — searchable by number, order reference and
 * customer email, with Order/Customer columns and CSV export. Read-only + no import (invoices are
 * immutable and code-issued). Adds a "Download all as PDF" button (combined multi-invoice PDF).
 */
class InvoiceAdmin extends ModelAdmin
{
    private static string $url_segment = 'invoices';

    private static string $menu_title = 'Invoices';

    private static string $menu_icon_class = 'font-icon-print';

    private static array $managed_models = [
        ShopInvoice::class,
    ];

    public $showImportForm = false;

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        $grid = $form->Fields()->dataFieldByName($this->sanitiseClassName($this->modelClass));
        if ($grid instanceof GridField) {
            $grid->getConfig()->addComponent(new DocumentsPrintButton());
        }

        return $form;
    }
}
