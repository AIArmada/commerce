<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Drivers\Tax;

use AIArmada\Affiliates\Contracts\TaxWorksheetWriter;
use AIArmada\Affiliates\Data\TaxWorksheetRequest;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class PrivateTaxWorksheetWriter implements TaxWorksheetWriter
{
    public function write(TaxWorksheetRequest $request): string
    {
        $payer = config('affiliates.tax.payer_info', []);
        $recipient = $request->recipient;
        $tin = preg_replace('/[^0-9]/', '', $recipient->tin) ?? '';
        $masked = mb_strlen($tin) >= 4 ? '***-**-' . mb_substr($tin, -4) : '***';

        $content = "1099-NEC preparation worksheet (not an official IRS filing)\n";
        $content .= "Tax Year: {$request->year}\n\n";
        $content .= "PAYER'S INFORMATION:\n";
        $content .= 'Name: ' . ($payer['name'] ?? config('app.name')) . "\n";
        $content .= 'Address: ' . ($payer['address'] ?? '') . "\n\n";
        $content .= "RECIPIENT'S INFORMATION:\n";
        $content .= "Name: {$recipient->legalName}\nAddress: {$recipient->address}\nTIN: {$masked}\n\n";
        $content .= 'Reported compensation: ' . MoneyFormatter::formatMinor($request->amountMinor, $request->currency) . "\n";

        if ($request->excludedNotes !== null) {
            $content .= "\n" . $request->excludedNotes . "\n";
        }

        $path = sprintf('tax-documents/%d/1099-NEC-%d-%s-%s.txt', $request->year, $request->year, $request->affiliateId, Str::uuid());

        if (! Storage::disk($this->disk())->put($path, $content, 'private')) {
            throw new RuntimeException('Tax worksheet could not be stored.');
        }

        return $path;
    }

    public function delete(string $path): void
    {
        Storage::disk($this->disk())->delete($path);
    }

    private function disk(): string
    {
        return (string) (config('affiliates.tax.storage_disk') ?? 'local');
    }
}
