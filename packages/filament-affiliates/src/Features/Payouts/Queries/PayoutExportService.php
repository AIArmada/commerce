<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Payouts\Queries;

use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionIntegrity;
use AIArmada\Affiliates\Features\Conversions\Api\PayoutExportRow;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutReconciliationQueries;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use BackedEnum;
use League\Csv\Writer;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;
use Spatie\LaravelPdf\Facades\Pdf;
use SplTempFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service for exporting affiliate payout data in multiple formats.
 *
 * Supports CSV, Excel (XLSX), and PDF formats.
 */
final class PayoutExportService
{
    /**
     * Download payout data as CSV.
     */
    public function downloadCsv(AffiliatePayout $payout): StreamedResponse
    {
        $payout = $this->resolveOwnerScopedPayout($payout);

        $filename = $this->sanitizeFilename($payout->reference, 'csv');

        return response()->streamDownload(
            function () use ($payout): void {
                $csv = Writer::createFromFileObject(new SplTempFileObject);
                $csv->insertOne($this->getHeaders());

                foreach ($this->conversionRows($payout) as $conversion) {
                    $csv->insertOne($this->getRowData($conversion));
                }

                echo $csv->toString();
            },
            $filename,
            ['Content-Type' => 'text/csv']
        );
    }

    /**
     * Download a multi-currency settlement summary as CSV.
     *
     * One row per payout, then per-currency legs and a single labeled
     * converted total with rate provenance for finance reconciliation.
     */
    public function downloadSettlementCsv(?string $startDate = null, ?string $endDate = null): StreamedResponse
    {
        $query = AffiliatePayout::query()->orderBy('currency')->orderBy('reference');

        if ((bool) config('affiliates.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            $includeGlobal = (bool) config('affiliates.owner.include_global', false);
            $query->withoutGlobalScope(OwnerScope::class);
            OwnerQuery::applyToEloquentBuilder($query, $owner, $includeGlobal);
        }

        if ($startDate !== null) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate !== null) {
            $query->where('created_at', '<=', $endDate);
        }

        $payouts = $query->with('payee')->get();
        $report = app(PayoutReconciliationQueries::class)->summarizePayoutIds($payouts->modelKeys(), $startDate, $endDate, (bool) config('affiliates.owner.include_global', false));
        $rows = $this->buildSettlementData($payouts, $report);

        return response()->streamDownload(
            function () use ($rows): void {
                $csv = Writer::createFromFileObject(new SplTempFileObject);

                foreach ($rows as $row) {
                    $csv->insertOne($row);
                }

                echo $csv->toString();
            },
            'settlement-summary.csv',
            ['Content-Type' => 'text/csv']
        );
    }

    /**
     * Download payout data as Excel (XLSX).
     *
     * Streams a genuine XLSX workbook using OpenSpout.
     */
    public function downloadExcel(AffiliatePayout $payout): StreamedResponse
    {
        $payout = $this->resolveOwnerScopedPayout($payout);

        $data = $this->buildExportData($payout);
        $filename = $this->sanitizeFilename($payout->reference, 'xlsx');

        return response()->streamDownload(
            function () use ($data): void {
                $writer = new XlsxWriter;
                $writer->openToFile('php://output');

                try {
                    foreach ($data as $row) {
                        $writer->addRow(Row::fromValues(array_values($row)));
                    }
                } finally {
                    $writer->close();
                }
            },
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    /**
     * Download payout data as PDF.
     *
     * Renders a genuine PDF using the configured Spatie Laravel PDF driver.
     */
    public function downloadPdf(AffiliatePayout $payout): StreamedResponse
    {
        $payout = $this->resolveOwnerScopedPayout($payout);

        $data = $this->buildExportData($payout);
        $filename = $this->sanitizeFilename($payout->reference, 'pdf');

        $html = $this->buildPdfHtml($payout, $data);
        $bytes = base64_decode(Pdf::html($html)->format('a4')->base64(), true);

        if ($bytes === false || ! str_starts_with($bytes, '%PDF-')) {
            throw new RuntimeException('Payout PDF renderer returned invalid PDF data.');
        }

        return response()->streamDownload(
            static fn () => print $bytes,
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    private function resolveOwnerScopedPayout(AffiliatePayout $payout): AffiliatePayout
    {
        if (! (bool) config('affiliates.owner.enabled', false)) {
            return $payout->exists
                ? AffiliatePayout::query()->whereKey($payout->getKey())->firstOrFail()
                : $payout;
        }

        $owner = OwnerContext::resolve();
        $includeGlobal = (bool) config('affiliates.owner.include_global', false);

        $query = AffiliatePayout::query()->withoutGlobalScope(OwnerScope::class);
        OwnerQuery::applyToEloquentBuilder($query, $owner, $includeGlobal);

        return $query
            ->whereKey($payout->getKey())
            ->firstOrFail();
    }

    /**
     * Stream conversion rows for the payout without hydrating them all at once.
     *
     * @return iterable<int, PayoutExportRow>
     */
    private function conversionRows(AffiliatePayout $payout): iterable
    {
        return app(PayoutConversionIntegrity::class)->exportRows((string) $payout->getKey(), $payout->owner_type, $payout->owner_id, spl_object_id($payout->getConnection()));
    }

    /**
     * Get export headers.
     *
     * @return array<string>
     */
    private function getHeaders(): array
    {
        return [
            'Affiliate Code',
            'Reference',
            'Commission Amount',
            'Currency',
            'Status',
            'Conversion Date',
        ];
    }

    /**
     * Get row data for a conversion.
     *
     * @return array<string>
     */
    private function getRowData(PayoutExportRow $conversion): array
    {
        return [
            $this->sanitizeCell((string) $conversion->affiliateCode),
            $this->sanitizeCell((string) $conversion->externalReference),
            $this->sanitizeCell(MoneyFormatter::decimalFromMinor((int) $conversion->commissionMinor, (string) $conversion->currency)),
            $this->sanitizeCell((string) $conversion->currency),
            $this->sanitizeCell($conversion->status),
            $this->sanitizeCell($conversion->createdAt ?? ''),
        ];
    }

    /**
     * Neutralize CSV/XLSX formula injection: cells starting with a formula
     * trigger character are prefixed with a single quote so spreadsheet
     * applications treat them as plain text.
     */
    private function sanitizeCell(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $first = $value[0];

        if (in_array($first, ['=', '+', '-', '@', "\t", "\r", "\n"], true)) {
            return "'" . $value;
        }

        return $value;
    }

    private function sanitizeFilename(?string $reference, string $extension): string
    {
        $base = (string) preg_replace('/[^A-Za-z0-9\-_]+/', '_', (string) $reference);
        $base = mb_trim($base, '_');

        if ($base === '') {
            $base = 'payout';
        }

        return mb_substr($base, 0, 120) . '.' . $extension;
    }

    /**
     * @param  iterable<int, AffiliatePayout>  $payouts
     * @param  array<string, mixed>  $report
     * @return array<int, array<string>>
     */
    private function buildSettlementData(iterable $payouts, array $report): array
    {
        $rows = [[
            'Reference',
            'Payee',
            'Amount',
            'Currency',
            'Status',
            'Conversions',
            'Created',
        ]];

        foreach ($payouts as $payout) {
            $payee = $payout->payee;
            $payeeCode = $payee instanceof Affiliate ? $payee->code : (string) $payout->payee_id;

            $rows[] = [
                $this->sanitizeCell((string) $payout->reference),
                $this->sanitizeCell((string) $payeeCode),
                $this->sanitizeCell(MoneyFormatter::decimalFromMinor((int) $payout->total_minor, (string) $payout->currency)),
                $this->sanitizeCell((string) $payout->currency),
                $this->sanitizeCell($this->stringifyStatus($payout->status)),
                $this->sanitizeCell((string) $payout->conversion_count),
                $this->sanitizeCell($payout->created_at?->format('Y-m-d H:i:s') ?? ''),
            ];
        }

        $rows[] = [];
        $rows[] = ['Leg Currency', 'Payouts', 'Leg Total', '', '', '', ''];

        foreach ($report['by_currency'] ?? [] as $currency => $leg) {
            $rows[] = [
                $this->sanitizeCell((string) $currency),
                $this->sanitizeCell((string) ($leg['count'] ?? 0)),
                $this->sanitizeCell(MoneyFormatter::decimalFromMinor((int) ($leg['total_minor'] ?? 0), (string) $currency)),
                '', '', '', '',
            ];
        }

        $summary = $report['summary'] ?? [];
        $conversion = $summary['conversion'] ?? null;

        if (($summary['converted'] ?? false) && is_array($conversion)) {
            $rows[] = [];
            $rows[] = [
                $this->sanitizeCell('CONVERTED TOTAL'),
                $this->sanitizeCell((string) ($summary['currency'] ?? '')),
                $this->sanitizeCell(MoneyFormatter::decimalFromMinor((int) ($summary['total_amount_minor'] ?? 0), (string) ($summary['currency'] ?? 'MYR'))),
                $this->sanitizeCell(sprintf(
                    'rates as of %s (%s)',
                    (string) ($conversion['as_of'] ?? ''),
                    (string) ($conversion['source'] ?? ''),
                )),
                '', '', '',
            ];
        }

        return $rows;
    }

    /**
     * Build export data array.
     *
     * @return array<int, array<string>>
     */
    private function buildExportData(AffiliatePayout $payout): array
    {
        $data = [$this->getHeaders()];

        foreach ($this->conversionRows($payout) as $conversion) {
            $data[] = $this->getRowData($conversion);
        }

        return $data;
    }

    /**
     * Build HTML content for PDF generation.
     *
     * @param  array<int, array<string>>  $data
     */
    private function buildPdfHtml(AffiliatePayout $payout, array $data): string
    {
        $headers = array_shift($data);
        $rows = $data;

        // Authoritative payout totals: payouts hold one currency by
        // construction, so never re-sum (and never mislabel) conversions.
        $totalCommissionMinor = (int) $payout->total_minor;
        $conversionCount = count($rows);
        $currency = (string) ($payout->currency ?? 'MYR');
        $formattedTotalCommission = MoneyFormatter::formatMinor($totalCommissionMinor, $currency);

        $reference = htmlspecialchars((string) $payout->reference, ENT_QUOTES, 'UTF-8');
        $status = htmlspecialchars($this->getStatusValue($payout), ENT_QUOTES, 'UTF-8');
        $generated = htmlspecialchars($payout->created_at->format('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8');
        $total = htmlspecialchars($formattedTotalCommission, ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payout Report - {$reference}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        h1 { color: #333; font-size: 18px; }
        .meta { margin-bottom: 20px; }
        .meta p { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f5f5f5; text-align: left; padding: 8px; border: 1px solid #ddd; }
        td { padding: 8px; border: 1px solid #ddd; }
        .total { font-weight: bold; background: #f9f9f9; }
        tr:nth-child(even) { background: #fafafa; }
    </style>
</head>
<body>
    <h1>Affiliate Payout Report</h1>
    <div class="meta">
        <p><strong>Reference:</strong> {$reference}</p>
        <p><strong>Status:</strong> {$status}</p>
        <p><strong>Generated:</strong> {$generated}</p>
        <p><strong>Total Conversions:</strong> {$conversionCount}</p>
        <p><strong>Total Amount:</strong> {$total}</p>
    </div>
    <table>
        <thead>
            <tr>
HTML;

        foreach ($headers ?? [] as $header) {
            $html .= sprintf('<th>%s</th>', htmlspecialchars((string) $header));
        }

        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= sprintf('<td>%s</td>', htmlspecialchars((string) $cell));
            }
            $html .= '</tr>';
        }

        $html .= <<<'HTML'
        </tbody>
    </table>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Get status value as string (handles model states, enums, and plain strings).
     */
    private function getStatusValue(AffiliatePayout $payout): string
    {
        return $this->stringifyStatus($payout->status);
    }

    private function stringifyStatus(mixed $status): string
    {
        if ($status instanceof BackedEnum) {
            return (string) $status->value;
        }

        if (is_object($status) && method_exists($status, 'getValue')) {
            /** @var mixed $value */
            $value = $status->getValue();

            return (string) $value;
        }

        return (string) $status;
    }
}
