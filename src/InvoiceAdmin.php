<?php

namespace SilverShop\Invoicing;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\GridField\GridFieldExportButton;

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
            // Invoices are immutable: view-only rows (no edit pencil), plus CSV export and combined PDF.
            $config = GridFieldConfig_RecordViewer::create();
            $config->addComponent(GridFieldExportButton::create('buttons-before-left'));
            $config->addComponent(new DocumentsPrintButton());
            $grid->setConfig($config);
            // Extension point: adjust the invoice grid (columns, buttons, config).
            $this->extend('updateDocumentsGridField', $grid);
        }

        return $form;
    }
}
