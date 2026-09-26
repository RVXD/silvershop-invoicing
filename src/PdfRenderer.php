<?php

namespace SilverShop\Invoicing;

use Dompdf\Dompdf;
use Dompdf\Options;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Renders a document's HTML (from its overridable template) to a PDF via dompdf. External resources
 * are disabled — the templates are self-contained (embedded CSS, no remote images).
 */
class PdfRenderer
{
    public static function pdf(string $html, ?string $paper = null): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');
        // Render print styles (ignore any @media screen chrome) and parse HTML5.
        $options->set('defaultMediaType', 'print');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper($paper ?: self::paperSize());
        $dompdf->loadHtml($html);
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Configured paper size (SiteConfig → Invoicing), defaulting to A4.
     */
    protected static function paperSize(): string
    {
        $config = SiteConfig::current_site_config();
        $size = $config && $config->hasMethod('InvoicePaperSizeOrDefault')
            ? $config->InvoicePaperSizeOrDefault()
            : 'A4';

        return in_array($size, ['A4', 'Letter'], true) ? $size : 'A4';
    }

    public static function download(string $html, string $filename): HTTPResponse
    {
        $response = HTTPResponse::create(self::pdf($html));
        $response->addHeader('Content-Type', 'application/pdf');
        $response->addHeader('Content-Disposition', 'inline; filename="' . $filename . '"');

        return $response;
    }
}
