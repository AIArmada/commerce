<?php

declare(strict_types=1);

namespace AIArmada\Docs\Drivers;

use AIArmada\Docs\Contracts\PdfRenderer;
use AIArmada\Docs\Data\PdfRenderOptions;
use Spatie\LaravelPdf\Facades\Pdf;

final readonly class SpatiePdfRenderer implements PdfRenderer
{
    public function render(string $html, PdfRenderOptions $options): string
    {
        $pdf = Pdf::html($html)
            ->format($options->format)
            ->orientation($options->orientation)
            ->margins($options->marginTop, $options->marginRight, $options->marginBottom, $options->marginLeft);

        if ($options->fullBleed) {
            $pdf->margins(0, 0, 0, 0);
        }

        if ($options->printBackground) {
            $pdf->withBrowsershot(static function ($browsershot): void {
                $browsershot->showBackground();
            });
        }

        return $pdf->generatePdfContent();
    }
}
