<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Drivers;

use AIArmada\FilamentAffiliates\Contracts\PayoutPdfRenderer;
use RuntimeException;
use Spatie\LaravelPdf\Facades\Pdf;

final class SpatiePayoutPdfRenderer implements PayoutPdfRenderer
{
    public function render(string $html): string
    {
        $driver = (string) config('filament-affiliates.exports.pdf_driver', 'browsershot');

        if (! in_array($driver, ['browsershot', 'chrome', 'dompdf', 'weasyprint'], true)) {
            throw new RuntimeException('Payout exports require a local PDF driver.');
        }

        $bytes = Pdf::html($html)->driver($driver)->format('a4')->dontCache()->generatePdfContent();

        if (! str_starts_with($bytes, '%PDF-')) {
            throw new RuntimeException('Payout PDF renderer returned invalid PDF data.');
        }

        return $bytes;
    }
}
