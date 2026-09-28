<?php

namespace SilverShop\Invoicing;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\ORM\DB;

/**
 * Enriches SilverShop's TaxClass for the "Tax" editor under Shop settings: explains the rate is a decimal
 * fraction, adds a single "default for new products" flag, a Products grid, and percentage/label helpers.
 *
 * @property bool $IsDefault
 */
class TaxClassCMSExtension extends Extension
{
    private static array $db = [
        // The class pre-selected on new products (see ProductInvoiceExtension). Only one is kept default.
        'IsDefault' => 'Boolean',
    ];

    public function updateCMSFields(FieldList $fields): void
    {
        $rate = $fields->dataFieldByName('Rate');
        if ($rate) {
            $rate->setDescription(_t(
                self::class . '.RATE_NOTE',
                'Enter as a decimal fraction: 0.21 = 21%, 0.09 = 9%, 0 = no tax.'
            ));
        }

        $title = $fields->dataFieldByName('Title');
        if ($title) {
            $title->setDescription(_t(
                self::class . '.TITLE_NOTE',
                'Shown on the product Tax-class dropdown, e.g. "Standard rate" or "Reduced rate — food".'
            ));
        }

        $fields->addFieldToTab('Root.Main', CheckboxField::create('IsDefault', 'Default for new products')
            ->setDescription(_t(
                self::class . '.DEFAULT_NOTE',
                'New products start with this tax class pre-selected. Only one class can be the default.'
            )));

        // Inside SiteConfig's nested edit form the scaffolder downgrades the Products has_many to a
        // load-everything token field (unusable with thousands of products) instead of the grid-on-a-tab
        // it produces at top level. Restore that: the same GridFieldConfig_RelationEditor scaffolding
        // would use, on a "Products" tab. (Products are normally assigned a class from their Pricing tab.)
        if ($fields->dataFieldByName('Products')) {
            $fields->removeByName('Products');
            $grid = GridField::create(
                'Products',
                _t(self::class . '.PRODUCTS', 'Products with this tax class'),
                $this->getOwner()->Products(),
                GridFieldConfig_RelationEditor::create()
            );
            $columns = $grid->getConfig()->getComponentByType(GridFieldDataColumns::class);
            if ($columns) {
                $columns->setDisplayFields(['InternalItemID' => 'SKU', 'Title' => 'Product']);
            }
            $fields->addFieldToTab('Root.Products', $grid);
        }
    }

    /**
     * Keep a single default: when this class is marked default, clear the flag on every other class.
     * Direct SQL so sibling writes don't recurse back into this hook.
     */
    protected function onAfterWrite(): void
    {
        $owner = $this->getOwner();
        if ($owner->IsDefault) {
            DB::prepared_query(
                'UPDATE "SilverShop_TaxClass" SET "IsDefault" = 0 WHERE "ID" != ? AND "IsDefault" = 1',
                [$owner->ID]
            );
        }
    }

    /**
     * A tax class that still has products assigned must not be deleted — that would silently strip the
     * rate from those products. Reassign them to another class first. (null = abstain, so admins can
     * otherwise delete unused classes.)
     */
    public function canDelete($member = null): ?bool
    {
        return $this->getOwner()->Products()->exists() ? false : null;
    }

    /**
     * The rate formatted as a percentage for the list view, e.g. 0.21 -> "21%".
     */
    public function getRatePercent(): string
    {
        $rate = (float) $this->getOwner()->Rate;
        return rtrim(rtrim(number_format($rate * 100, 2, '.', ''), '0'), '.') . '%';
    }

    /**
     * A tick / dash for the "Default" column of the list view.
     */
    public function getDefaultLabel(): string
    {
        return $this->getOwner()->IsDefault ? '✓' : '—';
    }
}
