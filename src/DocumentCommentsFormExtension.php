<?php

namespace SilverShop\Invoicing;

use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Core\Extension;

/**
 * Invoice / credit-memo detail forms are read-only (the document is immutable), which also freezes
 * their child GridFields. This runs after that read-only pass and swaps the frozen "Comments" grid for
 * a fresh editable one, so staff can still append to the document's history without being able to alter
 * its financial content.
 */
class DocumentCommentsFormExtension extends Extension
{
    public function updateItemEditForm(Form $form): void
    {
        $record = $this->getOwner()->getRecord();
        if (!($record instanceof ShopInvoice) && !($record instanceof ShopCreditMemo)) {
            return;
        }
        if (!$record->ID || !$record->canView()) {
            return;
        }

        $fields = $form->Fields();
        if (!$fields->dataFieldByName('Comments')) {
            return;
        }

        $grid = GridField::create(
            'Comments',
            'Comments',
            $record->Comments(),
            GridFieldConfig_RecordEditor::create()
        );

        $fields->replaceField('Comments', $grid);
        // The replacement must be bound to the form, or its add/edit buttons can't reach the record.
        $grid->setForm($form);
    }
}
