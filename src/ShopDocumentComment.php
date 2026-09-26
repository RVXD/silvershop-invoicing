<?php

namespace SilverShop\Invoicing;

use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * An append-only note in a document's history (invoice or credit memo). Optionally flagged to notify
 * the customer and/or be visible to them on the storefront. Immutable once written — the document's
 * financial content stays frozen; this is its comment trail (cf. Magento invoice comments).
 *
 * @property string $Body
 * @property bool   $NotifyCustomer
 * @property bool   $VisibleOnFront
 * @method   DataObject Document()
 * @method   Member     Author()
 */
class ShopDocumentComment extends DataObject
{
    private static string $table_name = 'ShopDocumentComment';

    private static string $singular_name = 'Comment';

    private static string $plural_name = 'Comments';

    private static array $db = [
        'Body' => 'Text',
        'NotifyCustomer' => 'Boolean',
        'VisibleOnFront' => 'Boolean',
    ];

    private static array $has_one = [
        'Document' => DataObject::class, // polymorphic: a ShopInvoice or ShopCreditMemo
        'Author' => Member::class,
    ];

    private static string $default_sort = '"Created" DESC';

    private static array $summary_fields = [
        'Created.Nice' => 'When',
        'Author.Name' => 'Author',
        'Body' => 'Note',
        'NotifyCustomer.Nice' => 'Notified',
        'VisibleOnFront.Nice' => 'Customer-visible',
    ];

    private static array $searchable_fields = ['Body'];

    protected function onBeforeWrite(): void
    {
        if (!$this->AuthorID && ($member = Security::getCurrentUser())) {
            $this->AuthorID = $member->ID;
        }

        parent::onBeforeWrite();
    }

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['DocumentID', 'DocumentClass', 'AuthorID']);

        return $fields;
    }

    public function canView($member = null): bool
    {
        return Permission::check('CMS_ACCESS', 'any', $member);
    }

    public function canCreate($member = null, $context = []): bool
    {
        return Permission::check('CMS_ACCESS', 'any', $member);
    }

    public function canEdit($member = null): bool
    {
        // Allow entering a new note; freeze it once saved (append-only history).
        return !$this->isInDB() && Permission::check('CMS_ACCESS', 'any', $member);
    }

    public function canDelete($member = null): bool
    {
        return Permission::check('ADMIN', 'any', $member);
    }
}
