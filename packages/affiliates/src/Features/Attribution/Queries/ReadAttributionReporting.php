<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Queries;

use AIArmada\Affiliates\Features\Attribution\Api\AttributionReportingReader;
use AIArmada\Affiliates\Features\Attribution\Api\AttributionReportingRecord;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateAttribution;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

final readonly class ReadAttributionReporting implements AttributionReportingReader
{
    public function available(): bool
    {
        try {
            $model = new AffiliateAttribution;

            return $model->getConnection()->getSchemaBuilder()->hasTable($model->getTable());
        } catch (Throwable) {
            return false;
        }
    }

    public function find(string $id): ?AttributionReportingRecord
    {
        if (! Str::isUuid($id) || ! $this->available()) {
            return null;
        }

        $row = AffiliateAttribution::query()->forOwner()->with('affiliate')->find($id);

        return $row === null ? null : $this->record($row);
    }

    public function latestForVoucher(string $voucherCode): ?AttributionReportingRecord
    {
        if (! $this->available()) {
            return null;
        }

        $row = AffiliateAttribution::query()->forOwner()->with('affiliate')
            ->whereRaw('LOWER(voucher_code) = ?', [mb_strtolower($voucherCode)])
            ->orderByDesc('last_seen_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $row === null ? null : $this->record($row);
    }

    public function options(string $column): array
    {
        if (! in_array($column, ['affiliate_code', 'source', 'medium', 'campaign'], true) || ! $this->available()) {
            return [];
        }

        /** @var array<string, string> $options */
        $options = AffiliateAttribution::query()->forOwner()->whereNotNull($column)
            ->distinct()->orderBy($column)->pluck($column, $column)->all();

        return $options;
    }

    public function count(?string $affiliateId = null): int
    {
        return AffiliateAttribution::query()->forOwner()
            ->when($affiliateId !== null, fn (Builder $query): Builder => $query->where('affiliate_id', $affiliateId))
            ->count();
    }

    public function hasAffiliateHistory(string $affiliateId, int $connectionIdentity): bool
    {
        $this->assertSharedConnection($connectionIdentity);

        return AffiliateAttribution::query()->withoutOwnerScope()->where('affiliate_id', $affiliateId)->exists();
    }

    public function countFirstSeen(string $affiliateId, DateTimeInterface $start, DateTimeInterface $end): int
    {
        return AffiliateAttribution::query()->forOwner()->where('affiliate_id', $affiliateId)
            ->whereBetween('first_seen_at', [$start, $end])->count();
    }

    public function countWindow(DateTimeInterface $start, DateTimeInterface $end): int
    {
        return $this->window($start, $end)->count();
    }

    public function subjectCounts(DateTimeInterface $start, DateTimeInterface $end): array
    {
        return $this->window($start, $end)->whereNotNull('subject_type')->whereNotNull('subject_key')
            ->toBase()
            ->selectRaw('subject_type, subject_key, MAX(subject_title_snapshot) as subject_title_snapshot, COUNT(*) as attributions')
            ->groupBy('subject_type', 'subject_key')->get()
            ->map(static fn (object $row): array => [
                'subject_type' => (string) $row->subject_type,
                'subject_key' => (string) $row->subject_key,
                'subject_title_snapshot' => $row->subject_title_snapshot === null ? null : (string) $row->subject_title_snapshot,
                'attributions' => (int) $row->attributions,
            ])->values()->all();
    }

    public function assertSharedConnection(int $connectionIdentity): void
    {
        if (spl_object_id((new AffiliateAttribution)->getConnection()) !== $connectionIdentity) {
            throw new LogicException('Attribution reporting requires a shared database connection.');
        }
    }

    /** @return Builder<AffiliateAttribution> */
    private function window(DateTimeInterface $start, DateTimeInterface $end): Builder
    {
        return AffiliateAttribution::query()->forOwner()->where(function (Builder $query) use ($start, $end): void {
            $query->whereBetween('first_seen_at', [$start, $end])
                ->orWhere(function (Builder $fallback) use ($start, $end): void {
                    $fallback->whereNull('first_seen_at')->whereBetween('created_at', [$start, $end]);
                });
        });
    }

    private function record(AffiliateAttribution $row): AttributionReportingRecord
    {
        return new AttributionReportingRecord($row->affiliate_code, $row->affiliate?->name, $row->source, $row->medium, $row->campaign);
    }
}
