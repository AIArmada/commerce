<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services\Tax;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliateTaxDocument;
use AIArmada\Affiliates\States\CompletedPayout;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class TaxDocumentService
{
    public function __construct(
        private readonly Tax1099Generator $generator,
    ) {}

    public function generateAnnualDocuments(int $year): Collection
    {
        $affiliates = $this->getAffiliatesRequiring1099($year);
        $documents = collect();

        foreach ($affiliates as $affiliate) {
            $document = $this->generate1099ForAffiliate($affiliate, $year);
            if ($document) {
                $documents->push($document);
            }
        }

        return $documents;
    }

    public function generate1099ForAffiliate(Affiliate $affiliate, int $year): ?AffiliateTaxDocument
    {
        $currency = $this->thresholdCurrency();
        $totalPayouts = $this->calculateAnnualPayouts($affiliate, $year, $currency);

        $threshold = config('affiliates.tax.1099_threshold', 60000);
        if ($totalPayouts < $threshold) {
            return null;
        }

        $excludedNotes = $this->excludedPayoutsNotes($affiliate, $year, $currency);
        $taxInfo = $affiliate->tax_info ?? [];
        if (empty($taxInfo['tin']) || empty($taxInfo['legal_name'])) {
            return AffiliateTaxDocument::create([
                'affiliate_id' => $affiliate->id,
                'document_type' => '1099-NEC',
                'tax_year' => $year,
                'status' => 'pending_info',
                'total_amount_minor' => $totalPayouts,
                'currency' => $currency,
                'notes' => 'Missing required tax information (TIN or legal name).' . $excludedNotes,
            ]);
        }

        $documentPath = $this->generator->generate([
            'affiliate' => $affiliate,
            'year' => $year,
            'total_amount' => $totalPayouts,
            'currency' => $currency,
            'tax_info' => $taxInfo,
            'excluded_notes' => $excludedNotes !== '' ? mb_trim($excludedNotes) : null,
        ]);

        return AffiliateTaxDocument::create([
            'affiliate_id' => $affiliate->id,
            'document_type' => '1099-NEC',
            'tax_year' => $year,
            'status' => 'generated',
            'total_amount_minor' => $totalPayouts,
            'currency' => $currency,
            'document_path' => $documentPath,
            'generated_at' => CarbonImmutable::now(),
            'notes' => $excludedNotes !== '' ? mb_trim($excludedNotes) : null,
        ]);
    }

    public function thresholdCurrency(): string
    {
        return mb_strtoupper((string) config('affiliates.tax.1099_threshold_currency', 'USD'));
    }

    /**
     * Affiliates whose completed threshold-currency payouts meet the 1099 floor.
     *
     * Only threshold-currency payouts count toward the threshold and the
     * reported total; anything else is conversion-rate fiction on a tax
     * filing. Excluded payouts are disclosed, never blended: see
     * excludedPayoutsForYear().
     */
    public function getAffiliatesRequiring1099(int $year): Collection
    {
        $threshold = config('affiliates.tax.1099_threshold', 60000);
        $currency = $this->thresholdCurrency();
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        $affiliateIds = AffiliatePayout::query()
            ->where('payee_type', (new Affiliate)->getMorphClass())
            ->where('status', CompletedPayout::value())
            ->where('currency', $currency)
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->groupBy('payee_id')
            ->havingRaw('SUM(total_minor) >= ?', [$threshold])
            ->pluck('payee_id');

        return Affiliate::query()
            ->whereIn('id', $affiliateIds)
            ->get();
    }

    /**
     * Completed threshold-currency payouts for the year.
     */
    public function calculateAnnualPayouts(Affiliate $affiliate, int $year, ?string $currency = null): int
    {
        $currency ??= $this->thresholdCurrency();
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        return (int) $affiliate->payouts()
            ->where('status', CompletedPayout::value())
            ->where('currency', $currency)
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->sum('total_minor');
    }

    /**
     * Completed payouts the 1099 total skips for being outside the threshold
     * currency. Surfaces foreign earners for manual tax-team review.
     *
     * @return Collection<int, AffiliatePayout>
     */
    public function excludedPayoutsForYear(Affiliate $affiliate, int $year, ?string $currency = null): Collection
    {
        $currency ??= $this->thresholdCurrency();
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        return $affiliate->payouts()
            ->where('status', CompletedPayout::value())
            ->where('currency', '!=', $currency)
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->orderBy('paid_at')
            ->get();
    }

    private function excludedPayoutsNotes(Affiliate $affiliate, int $year, string $currency): string
    {
        $excluded = $this->excludedPayoutsForYear($affiliate, $year, $currency);

        if ($excluded->isEmpty()) {
            return '';
        }

        $legs = $excluded->map(fn (AffiliatePayout $payout): string => sprintf(
            '%s (%s %s)',
            $payout->reference,
            mb_strtoupper((string) $payout->currency),
            number_format($payout->total_minor / 100, 2),
        ))->implode(', ');

        return sprintf(' Excluded non-%s payouts for manual review: %s.', $currency, $legs);
    }

    public function getDocumentsForAffiliate(Affiliate $affiliate): Collection
    {
        return AffiliateTaxDocument::query()
            ->where('affiliate_id', $affiliate->id)
            ->orderByDesc('tax_year')
            ->get();
    }

    public function markDocumentAsSent(AffiliateTaxDocument $document): AffiliateTaxDocument
    {
        $document->update([
            'status' => 'sent',
            'sent_at' => CarbonImmutable::now(),
        ]);

        return $document;
    }

    public function regenerateDocument(AffiliateTaxDocument $document): AffiliateTaxDocument
    {
        $affiliate = $document->affiliate;

        $currency = mb_strtoupper((string) ($document->currency ?? $this->thresholdCurrency()));
        $excludedNotes = $this->excludedPayoutsNotes($affiliate, $document->tax_year, $currency);

        $documentPath = $this->generator->generate([
            'affiliate' => $affiliate,
            'year' => $document->tax_year,
            'total_amount' => $document->total_amount_minor,
            'currency' => $currency,
            'tax_info' => $affiliate->tax_info ?? [],
            'excluded_notes' => $excludedNotes !== '' ? mb_trim($excludedNotes) : null,
        ]);

        $document->update([
            'document_path' => $documentPath,
            'status' => 'generated',
            'generated_at' => CarbonImmutable::now(),
        ]);

        return $document;
    }
}
