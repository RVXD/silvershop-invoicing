<?php

namespace SilverShop\Invoicing;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Permission;

/**
 * Internal per-series counter behind gap-free, concurrency-safe document numbering.
 *
 * One row per number series (e.g. "INV-2026", "CM-2026", or "INV" for a continuous series).
 * {@link reserve()} advances the counter with a row-locking UPDATE, so concurrent callers are
 * serialised and each receives a distinct number. Called inside the document's own write
 * transaction (see InvoiceService / CreditMemoService), a rolled-back write releases the number
 * instead of leaving a gap. Not exposed in the CMS.
 *
 * @property string $SeriesKey
 * @property int    $NextNumber
 */
class DocumentNumberSequence extends DataObject
{
    private static string $table_name = 'ShopDocumentNumberSequence';

    private static array $db = [
        'SeriesKey' => 'Varchar(64)',
        'NextNumber' => 'Int',
    ];

    private static array $indexes = [
        'SeriesKey' => ['type' => 'unique', 'columns' => ['SeriesKey']],
    ];

    /**
     * Reserve and return the next number for the given series. Concurrency-safe (InnoDB row lock);
     * gap-free when run inside the caller's transaction (a rolled-back write releases the number).
     */
    public static function reserve(string $seriesKey, int $start = 1): int
    {
        $table = DataObject::getSchema()->tableName(self::class);

        // Ensure the counter row exists (first document of a series). INSERT IGNORE is a no-op when the
        // row already exists (unique SeriesKey), so this is race-safe with no ORM round-trip.
        DB::prepared_query(
            "INSERT IGNORE INTO \"$table\" (\"ClassName\", \"Created\", \"LastEdited\", \"SeriesKey\", \"NextNumber\") "
            . 'VALUES (?, NOW(), NOW(), ?, ?)',
            [self::class, $seriesKey, $start]
        );

        // Row-locking advance, then read back the value we hold (same connection / transaction).
        DB::prepared_query(
            "UPDATE \"$table\" SET \"NextNumber\" = \"NextNumber\" + 1, \"LastEdited\" = NOW() WHERE \"SeriesKey\" = ?",
            [$seriesKey]
        );
        $advanced = (int) DB::prepared_query(
            "SELECT \"NextNumber\" FROM \"$table\" WHERE \"SeriesKey\" = ?",
            [$seriesKey]
        )->value();

        return $advanced - 1;
    }

    public function canView($member = null): bool
    {
        return Permission::check('ADMIN', 'any', $member);
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
}
