<?php

namespace SilverShop\Invoicing;

use SilverShop\ORM\FieldType\ShopCurrency;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;

/**
 * Shared behaviour for immutable, sequentially-numbered financial documents (invoice, credit memo):
 * assigns a `Number` (prefixed via the class's `number_prefix` config) and `Issued` date on create,
 * and blocks any change or deletion afterwards. The using class must declare `Number` + `Issued` in
 * `$db` and a `number_prefix` config.
 */
trait NumberedImmutableDocument
{
    protected function onBeforeWrite(): void
    {
        if (!$this->isInDB()) {
            if (!$this->Number) {
                $this->Number = $this->generateNumber();
            }
            if (!$this->Issued) {
                $this->Issued = DBDatetime::now()->getValue();
            }
        } elseif ($this->isChanged()) {
            throw new ValidationException(static::class . ' records are immutable once issued.');
        }

        parent::onBeforeWrite();
    }

    protected function generateNumber(): string
    {
        $prefix = (string) $this->config()->get('number_prefix');
        $next = (int) static::get()->count() + 1;

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    public function canEdit($member = null): bool
    {
        return false;
    }

    public function canDelete($member = null): bool
    {
        return false;
    }

    public function canCreate($member = null, $context = []): bool
    {
        return false;
    }

    /**
     * Template helpers: split the snapshot lines into product lines (the itemised table) and
     * adjustment lines (the totals block), with the item subtotal.
     */
    public function ItemLines()
    {
        return $this->Lines()->filter('Kind', 'Item');
    }

    public function ModifierLines()
    {
        return $this->Lines()->filter('Kind', 'Modifier');
    }

    public function Subtotal(): DBField
    {
        return DBField::create_field(ShopCurrency::class, (float) $this->ItemLines()->sum('Amount'));
    }
}
