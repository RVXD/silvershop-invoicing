<?php

namespace SilverShop\Invoicing;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridField;

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
            $grid->getConfig()->addComponent(new DocumentsPrintButton());
            // Extension point: adjust the credit-memo grid (columns, buttons, config).
            $this->extend('updateDocumentsGridField', $grid);
        }

        return $form;
    }
}
