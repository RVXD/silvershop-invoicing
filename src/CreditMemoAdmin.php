<?php

namespace SilverShop\Invoicing;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\GridField\GridFieldExportButton;

/**
 * Global, read-only view of every issued credit memo. Filterable, Order/Customer columns, CSV export,
 * no import (credit memos are immutable and code-issued). Adds a "Download all as PDF" button.
 */
class CreditMemoAdmin extends ModelAdmin
{
    private static string $url_segment = 'credit-memos';

    private static string $menu_title = 'Credit Memos';

    private static string $menu_icon_class = 'font-icon-back-in-time';

    private static array $managed_models = [
        ShopCreditMemo::class,
    ];

    public $showImportForm = false;

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        $grid = $form->Fields()->dataFieldByName($this->sanitiseClassName($this->modelClass));
        if ($grid instanceof GridField) {
            // Credit memos are immutable: view-only rows (no edit pencil), plus CSV export and combined PDF.
            $config = GridFieldConfig_RecordViewer::create();
            $config->addComponent(GridFieldExportButton::create('buttons-before-left'));
            $config->addComponent(new DocumentsPrintButton());
            $grid->setConfig($config);
            // Extension point: adjust the credit-memo grid (columns, buttons, config).
            $this->extend('updateDocumentsGridField', $grid);
        }

        return $form;
    }
}
