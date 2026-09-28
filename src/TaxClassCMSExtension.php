<?php

namespace SilverShop\Invoicing;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldDataColumns;

/**
 * Makes SilverShop's scaffolded TaxClass form usable in {@link TaxClassAdmin}: explains that the rate is
 * a decimal fraction, and exposes a percentage label for the list view.
 */
class TaxClassCMSExtension extends Extension
{
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

        // Inside SiteConfig's nested edit form the scaffolder downgrades the Products has_many to a
        // load-everything token field (unusable with thousands of products) instead of the grid-on-a-tab
        // it would produce at top level. Restore that: a read-only, paginated, filterable grid on a
        // "Products" tab. (Products are assigned to a class from their own Pricing tab, not here.)
        if ($fields->dataFieldByName('Products')) {
            $fields->removeByName('Products');
            $grid = GridField::create(
                'Products',
                _t(self::class . '.PRODUCTS', 'Products with this tax class'),
                $this->getOwner()->Products(),
                GridFieldConfig_Base::create()
            );
            $columns = $grid->getConfig()->getComponentByType(GridFieldDataColumns::class);
            if ($columns) {
                $columns->setDisplayFields(['InternalItemID' => 'SKU', 'Title' => 'Product']);
            }
            $fields->addFieldToTab('Root.Products', $grid);
        }
    }

    /**
     * The rate formatted as a percentage for the admin list, e.g. 0.21 -> "21%".
     */
    public function getRatePercent(): string
    {
        $rate = (float) $this->getOwner()->Rate;
        return rtrim(rtrim(number_format($rate * 100, 2, '.', ''), '0'), '.') . '%';
    }
}
