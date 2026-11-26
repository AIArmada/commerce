<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Queries;

use AIArmada\Affiliates\Features\Attribution\Api\AttributionReportingReader;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionReportingReader;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionReportRow;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionTrafficBreakdown;
use AIArmada\Affiliates\Features\Conversions\Api\VoucherConversionRecord;
use AIArmada\Affiliates\Models\AffiliateConversion;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use stdClass;
use Throwable;

final readonly class ReadConversionReporting implements ConversionReportingReader
{
    public function __construct(private AttributionReportingReader $attributions) {}

    public function available(): bool
    {
        try {
            $model = new AffiliateConversion;

            return $model->getConnection()->getSchemaBuilder()->hasTable($model->getTable());
        } catch (Throwable) {
            return false;
        }
    }

    public function money(?DateTimeInterface $start = null, ?DateTimeInterface $end = null, ?string $affiliateId = null): array
    {
        return $this->project($this->window($start, $end, $affiliateId)->toBase()
            ->selectRaw($this->moneyExpression())
            ->groupBy('commission_currency')->get());
    }

    public function byAffiliate(DateTimeInterface $start, DateTimeInterface $end): array
    {
        return $this->project($this->window($start, $end)->toBase()
            ->selectRaw('affiliate_id, MAX(affiliate_code) AS affiliate_code, ' . $this->moneyExpression())
            ->groupBy('affiliate_id', 'commission_currency')->get());
    }

    public function byDay(DateTimeInterface $start, DateTimeInterface $end): array
    {
        return $this->project($this->window($start, $end)->toBase()
            ->selectRaw('DATE(occurred_at) AS date, ' . $this->moneyExpression())
            ->groupBy('date', 'commission_currency')->orderBy('date')->get());
    }

    public function bySubject(DateTimeInterface $start, DateTimeInterface $end): array
    {
        return $this->project($this->window($start, $end)
            ->whereNotNull('subject_type')->whereNotNull('subject_key')->toBase()
            ->selectRaw('subject_type, subject_key, MAX(subject_title_snapshot) AS subject_title_snapshot, ' . $this->moneyExpression())
            ->groupBy('subject_type', 'subject_key', 'commission_currency')->get());
    }

    public function traffic(?DateTimeInterface $start = null, ?DateTimeInterface $end = null, ?string $affiliateId = null): ConversionTrafficBreakdown
    {
        $model = new AffiliateConversion;
        $this->attributions->assertSharedConnection(spl_object_id($model->getConnection()));
        $conversions = $model->getTable();
        $attributions = (string) config('affiliates.database.tables.attributions', 'affiliate_attributions');

        $rows = $this->window($start, $end, $affiliateId)
            ->join($attributions, static function (JoinClause $join) use ($conversions, $attributions): void {
                $join->on("{$attributions}.id", '=', "{$conversions}.affiliate_attribution_id")
                    ->on("{$attributions}.affiliate_id", '=', "{$conversions}.affiliate_id");

                if (config('affiliates.owner.enabled', false)) {
                    foreach (['owner_type', 'owner_id'] as $column) {
                        $left = "{$attributions}.{$column}";
                        $right = "{$conversions}.{$column}";
                        $join->where(static fn ($query) => $query->whereColumn($left, $right)
                            ->orWhere(static fn ($query) => $query->whereNull($left)->whereNull($right)));
                    }
                }
            })
            ->toBase()
            ->selectRaw("{$attributions}.source AS source, {$attributions}.campaign AS campaign, COUNT(*) AS conversions")
            ->groupBy("{$attributions}.source", "{$attributions}.campaign")->get();

        $sources = [];
        $campaigns = [];

        foreach ($rows as $row) {
            if (is_string($row->source) && $row->source !== '') {
                $sources[$row->source] = ($sources[$row->source] ?? 0) + (int) $row->conversions;
            }

            if (is_string($row->campaign) && $row->campaign !== '') {
                $campaigns[$row->campaign] = ($campaigns[$row->campaign] ?? 0) + (int) $row->conversions;
            }
        }

        return new ConversionTrafficBreakdown($sources, $campaigns);
    }

    public function voucher(string $voucherCode, ?string $orderReference): ?VoucherConversionRecord
    {
        if (! $this->available()) {
            return null;
        }

        $query = AffiliateConversion::query()->with('affiliate')
            ->whereRaw('LOWER(voucher_code) = ?', [mb_strtolower($voucherCode)])
            ->orderByDesc('occurred_at')->orderByDesc('created_at')->orderByDesc('id');

        if ($orderReference !== null) {
            $query->whereRaw('LOWER(external_reference) = ?', [mb_strtolower($orderReference)]);
            $row = $query->first();
        } else {
            $matches = $query->limit(2)->get();
            $row = $matches->count() === 1 ? $matches->first() : null;
        }

        return $row === null ? null : new VoucherConversionRecord(
            affiliateId: $row->affiliate_id,
            affiliateCode: $row->affiliate_code,
            affiliateName: $row->affiliate?->name,
            attributionId: $row->affiliate_attribution_id,
            ownerType: $row->owner_type,
            ownerId: $row->owner_id,
            connectionIdentity: spl_object_id($row->getConnection()),
        );
    }

    /** @return Builder<AffiliateConversion> */
    private function window(?DateTimeInterface $start, ?DateTimeInterface $end, ?string $affiliateId = null): Builder
    {
        $model = new AffiliateConversion;
        $query = AffiliateConversion::query()->forOwner();

        if ($start !== null) {
            $query->where($model->qualifyColumn('occurred_at'), '>=', $start);
        }

        if ($end !== null) {
            $query->where($model->qualifyColumn('occurred_at'), '<=', $end);
        }

        if ($affiliateId !== null) {
            $query->where($model->qualifyColumn('affiliate_id'), $affiliateId);
        }

        return $query;
    }

    private function moneyExpression(): string
    {
        return 'commission_currency AS currency, COUNT(*) AS conversions, COALESCE(SUM(value_minor), 0) AS revenue_minor, COALESCE(SUM(commission_minor), 0) AS commission_minor';
    }

    /**
     * @param Collection<int, stdClass> $rows
     * @return list<ConversionReportRow>
     */
    private function project(Collection $rows): array
    {
        return $rows->map(static fn (stdClass $row): ConversionReportRow => new ConversionReportRow(
            currency: isset($row->currency) ? (string) $row->currency : null,
            conversions: (int) $row->conversions,
            revenueMinor: (int) $row->revenue_minor,
            commissionMinor: (int) $row->commission_minor,
            affiliateId: isset($row->affiliate_id) ? (string) $row->affiliate_id : null,
            affiliateCode: isset($row->affiliate_code) ? (string) $row->affiliate_code : null,
            date: isset($row->date) ? (string) $row->date : null,
            subjectType: isset($row->subject_type) ? (string) $row->subject_type : null,
            subjectKey: isset($row->subject_key) ? (string) $row->subject_key : null,
            subjectTitleSnapshot: isset($row->subject_title_snapshot) ? (string) $row->subject_title_snapshot : null,
        ))->values()->all();
    }
}
