<?php

namespace SilverShop\Invoicing;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;

/**
 * Serves an invoice / credit memo as a printable HTML page or a downloadable PDF. CMS access only.
 *
 * Routes (see _config/routes.yml, rooted at "invoicing"):
 *   invoicing/invoice/<id>[/pdf]
 *   invoicing/creditmemo/<id>[/pdf]
 */
class ShopDocumentController extends Controller
{
    private static array $url_handlers = [
        'invoice/$ID/$Format' => 'invoice',
        'creditmemo/$ID/$Format' => 'creditmemo',
    ];

    private static array $allowed_actions = [
        'invoice',
        'creditmemo',
    ];

    protected function init(): void
    {
        parent::init();

        // Guests are sent to login; per-document canView() then allows staff or the order's owner.
        if (!Security::getCurrentUser()) {
            Security::permissionFailure($this);
        }
    }

    public function invoice(HTTPRequest $request): HTTPResponse
    {
        return $this->output(ShopInvoice::get()->byID((int) $request->param('ID')), $request->param('Format'));
    }

    public function creditmemo(HTTPRequest $request): HTTPResponse
    {
        return $this->output(ShopCreditMemo::get()->byID((int) $request->param('ID')), $request->param('Format'));
    }

    protected function output(?DataObject $document, ?string $format): HTTPResponse
    {
        if (!$document || !$document->canView()) {
            return $this->httpError(404);
        }

        $html = (string) $document->forTemplate();

        if (strtolower((string) $format) === 'pdf') {
            return PdfRenderer::download($html, $document->Number . '.pdf');
        }

        $response = HTTPResponse::create($html);
        $response->addHeader('Content-Type', 'text/html; charset=utf-8');

        return $response;
    }
}
