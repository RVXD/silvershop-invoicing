<?php

declare(strict_types=1);

namespace SilverShop\Invoicing;

use SilverShop\Reports\ShopPeriodReport;
use SilverShop\Reports\ShopReportQuery;
use SilverStripe\ORM\Queries\SQLSelect;

/**
 * Refunds issued via credit memos across the shop, over a period. Complements core's Shop Sales report
 * (which has no refund figures). Lives in the invoicing module because credit memos are its concern.
 */
class CreditMemoReport extends ShopPeriodReport
{
    protected $title = 'Credit memos (refunds)';

    protected $description = 'Monitor refunds issued via credit memos for a period. Group results by year, month, or day.';

    protected $dataClass = ShopCreditMemo::class;

    protected $periodfield = '"ShopCreditMemo"."Issued"';

    protected $grouping = true;

    public function columns(): array
    {
        $period = isset($_GET['filters']['Grouping']) ? $_GET['filters']['Grouping'] : 'Month';

        return [
            'FilterPeriod' => $period,
            'Count' => 'Credit memos',
            'Refunds' => 'Total refunded',
        ];
    }

    public function query(array $params): ShopReportQuery|SQLSelect
    {
        return parent::query($params)
            ->addWhere('"ShopCreditMemo"."Status" = \'Issued\'')
            ->selectField('COUNT("ShopCreditMemo"."ID")', 'Count')
            ->selectField('SUM("ShopCreditMemo"."Total")', 'Refunds');
    }
}
