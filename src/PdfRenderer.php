<?php

namespace SilverShop\Invoicing;

use Dompdf\Dompdf;
use Dompdf\Options;
use SilverStripe\Control\HTTPResponse;

/**
 * Renders a document's HTML (from its overridable template) to a PDF via dompdf. External resources
 * are disabled — the templates are self-contained (embedded CSS, no remote images).
 */
class PdfRenderer
{
    public static function pdf(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4');
        $dompdf->loadHtml($html);
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public static function download(string $html, string $filename): HTTPResponse
    {
        $response = HTTPResponse::create(self::pdf($html));
        $response->addHeader('Content-Type', 'application/pdf');
        $response->addHeader('Content-Disposition', 'inline; filename="' . $filename . '"');

        return $response;
    }
}
