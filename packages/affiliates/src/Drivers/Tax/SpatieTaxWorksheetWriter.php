<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Drivers\Tax;

use AIArmada\Affiliates\Contracts\TaxWorksheetWriter;
use AIArmada\Affiliates\Data\TaxWorksheetRequest;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Spatie\LaravelPdf\Facades\Pdf;

final class SpatieTaxWorksheetWriter implements TaxWorksheetWriter
{
    public function write(TaxWorksheetRequest $request): string
    {
        if (! Str::isUuid($request->affiliateId) || $request->year < 1900 || $request->year > 9999) {
            throw new InvalidArgumentException('Invalid tax worksheet identity or year.');
        }

        $payer = config('affiliates.tax.payer_info', []);
        $tin = preg_replace('/[^0-9]/', '', $request->recipient->tin) ?? '';
        $driver = (string) config('affiliates.tax.pdf_driver', 'browsershot');

        if (! in_array($driver, ['browsershot', 'chrome', 'dompdf', 'weasyprint'], true)) {
            throw new RuntimeException('Tax worksheets require a local PDF driver.');
        }

        $content = Pdf::view('affiliates::pdf.tax-worksheet', [
            'year' => $request->year,
            'payerName' => $payer['name'] ?? config('app.name'),
            'payerAddress' => $payer['address'] ?? '',
            'recipientName' => $request->recipient->legalName,
            'recipientAddress' => $request->recipient->address,
            'maskedTin' => mb_strlen($tin) >= 4 ? '***-**-' . mb_substr($tin, -4) : '***',
            'amount' => MoneyFormatter::formatMinor($request->amountMinor, $request->currency),
            'currency' => $request->currency,
            'excludedNotes' => $request->excludedNotes,
        ])->driver($driver)->format('letter')->margins(16, 16, 16, 16)->dontCache()->generatePdfContent();

        if (! str_starts_with($content, '%PDF-')) {
            throw new RuntimeException('Tax worksheet renderer did not return a PDF.');
        }

        $path = sprintf('tax-documents/%d/1099-NEC-%d-%s-%s.pdf', $request->year, $request->year, $request->affiliateId, Str::uuid());

        if (! Storage::disk($this->disk())->put($path, $content, 'private')) {
            throw new RuntimeException('Tax worksheet could not be stored.');
        }

        return $path;
    }

    public function delete(string $path): void
    {
        if (! preg_match('~\A(?:tax-documents/)([0-9]{4})/1099-NEC-\1-[0-9a-fA-F-]{36}-[0-9a-fA-F-]{36}\.pdf\z~D', $path)) {
            throw new InvalidArgumentException('Invalid tax worksheet storage path.');
        }

        $disk = Storage::disk($this->disk());

        if (! $disk->delete($path) && $disk->exists($path)) {
            throw new RuntimeException('Tax worksheet could not be deleted.');
        }
    }

    private function disk(): string
    {
        return (string) (config('affiliates.tax.storage_disk') ?? 'local');
    }
}
