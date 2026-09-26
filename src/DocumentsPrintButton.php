<?php

namespace SilverShop\Invoicing;

use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\GridField\AbstractGridFieldComponent;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Forms\GridField\GridField_URLHandler;

/**
 * Adds a "Download all as PDF" button to an invoice / credit-memo grid: renders every document in the
 * grid's current (filtered / searched) list into one combined PDF, one document per page — the
 * equivalent of Magento's mass "Print Invoices" action.
 */
class DocumentsPrintButton extends AbstractGridFieldComponent implements
    GridField_HTMLProvider,
    GridField_ActionProvider,
    GridField_URLHandler
{
    protected string $targetFragment;

    public function __construct(string $targetFragment = 'buttons-before-right')
    {
        $this->targetFragment = $targetFragment;
    }

    public function getHTMLFragments($gridField)
    {
        $button = new GridField_FormAction(
            $gridField,
            'downloadpdf',
            _t(self::class . '.DOWNLOAD_PDF', 'Download all as PDF'),
            'downloadpdf',
            null
        );
        $button->setIcon('print');
        $button->addExtraClass('btn btn-secondary no-ajax');
        $button->setForm($gridField->getForm());

        return [$this->targetFragment => $button->Field()];
    }

    public function getActions($gridField)
    {
        return ['downloadpdf'];
    }

    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName === 'downloadpdf') {
            return $this->handleDownload($gridField);
        }

        return null;
    }

    public function getURLHandlers($gridField)
    {
        return ['downloadpdf' => 'handleDownload'];
    }

    public function handleDownload($gridField, $request = null): ?HTTPResponse
    {
        $documents = [];
        foreach ($gridField->getManipulatedList() as $document) {
            if ($document->hasMethod('forTemplate') && $document->canView()) {
                $documents[] = $document;
            }
        }

        if (!$documents) {
            return null;
        }

        $response = HTTPResponse::create(PdfRenderer::combined($documents));
        $response->addHeader('Content-Type', 'application/pdf');
        $response->addHeader('Content-Disposition', 'inline; filename="documents-' . date('Y-m-d') . '.pdf"');

        return $response;
    }
}
