<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services\Tax;

use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Support\Facades\Storage;

final class Tax1099Generator
{
    public function generate(array $data): string
    {
        $affiliate = $data['affiliate'];
        $year = $data['year'];
        $totalAmount = $data['total_amount'];
        $taxInfo = $data['tax_info'];

        $filename = sprintf(
            '1099-NEC-%s-%s-%s.pdf',
            $year,
            $affiliate->id,
            now()->format('Ymd')
        );

        $path = "tax-documents/{$year}/{$filename}";

        $content = $this->generatePdfContent($affiliate, $year, $totalAmount, $taxInfo);

        Storage::disk(config('affiliates.tax.storage_disk', 'local'))
            ->put($path, $content);

        return $path;
    }

    private function generatePdfContent(
        Affiliate $affiliate,
        int $year,
        int $totalAmountMinor,
        array $taxInfo
    ): string {
        $payerInfo = config('affiliates.tax.payer_info', [
            'name' => config('app.name'),
            'address' => '',
            'tin' => '',
        ]);

        $content = "IRS Form 1099-NEC (Nonemployee Compensation)\n";
        $content .= "Tax Year: {$year}\n\n";

        $content .= "PAYER'S INFORMATION:\n";
        $content .= "Name: {$payerInfo['name']}\n";
        $content .= "Address: {$payerInfo['address']}\n";
        $content .= "TIN: {$payerInfo['tin']}\n\n";

        $content .= "RECIPIENT'S INFORMATION:\n";
        $content .= "Name: {$taxInfo['legal_name']}\n";
        $content .= 'Address: '.($taxInfo['address'] ?? '')."\n";
        $content .= 'TIN: '.$this->maskTin($taxInfo['tin'])."\n\n";

        $content .= 'Box 1 - Nonemployee Compensation: $'.number_format($totalAmountMinor / 100, 2)."\n";

        return $content;
    }

    private function maskTin(string $tin): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $tin);

        if (mb_strlen($cleaned) === 9) {
            return 'XXX-XX-'.mb_substr($cleaned, -4);
        }

        return 'XX-XXX'.mb_substr($cleaned, -4);
    }
}
