<?php

namespace SilverShop\Invoicing;

use SilverStripe\Admin\ModelAdmin;

/**
 * Global, read-only view of every issued credit memo. Filterable, Order/Customer columns, CSV export,
 * no import (credit memos are immutable and code-issued).
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
}
