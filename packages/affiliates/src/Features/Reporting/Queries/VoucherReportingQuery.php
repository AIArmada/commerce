<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Reporting\Queries;

use AIArmada\Affiliates\Features\Reporting\Api\VoucherReportingContext;
use AIArmada\Affiliates\Features\Reporting\Api\VoucherReportingReader;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final readonly class VoucherReportingQuery implements VoucherReportingReader
{
    public function available(): bool
    {
        return $this->hasTableFor(AffiliateConversion::class) || $this->hasTableFor(AffiliateAttribution::class);
    }

    public function resolve(string $voucherCode, ?string $orderReference): ?VoucherReportingContext
    {
        $voucherCode = mb_strtolower($voucherCode);
        $orderReference = $orderReference === null ? null : mb_strtolower($orderReference);
        $conversion = $this->findMatchingConversion($voucherCode, $orderReference);
        $context = $conversion === null ? null : $this->contextFromConversion($conversion);

        if ($context === null || ! array_filter($context, fn (mixed $value): bool => $this->normalizeString($value) !== null)) {
            $attribution = $this->findMatchingAttribution($voucherCode);
            $context = $attribution === null ? null : $this->contextFromAttribution($attribution);
        }

        if ($context === null) {
            return null;
        }

        return new VoucherReportingContext(
            affiliateCode: $this->normalizeString($context['affiliate_code']),
            affiliateName: $this->normalizeString($context['affiliate_name']),
            source: $this->normalizeString($context['source']),
            medium: $this->normalizeString($context['medium']),
            campaign: $this->normalizeString($context['campaign']),
        );
    }

    /** @return array<string, string> */
    public function affiliateOptions(): array
    {
        if ($this->hasTableFor(Affiliate::class)) {
            /** @var array<string, string> $options */
            $options = Affiliate::query()
                ->orderBy('name')
                ->orderBy('code')
                ->get(['code', 'name'])
                ->mapWithKeys(function (Model $affiliate): array {
                    $code = $this->normalizeString($affiliate->getAttribute('code'));

                    if ($code === null) {
                        return [];
                    }

                    $name = $this->normalizeString($affiliate->getAttribute('name'));

                    return [$code => $name !== null ? "{$name} ({$code})" : $code];
                })
                ->all();

            return $options;
        }

        if (! $this->hasTableFor(AffiliateAttribution::class)) {
            return [];
        }

        /** @var array<string, string> $options */
        $options = AffiliateAttribution::query()
            ->whereNotNull('affiliate_code')
            ->distinct()
            ->orderBy('affiliate_code')
            ->pluck('affiliate_code', 'affiliate_code')
            ->all();

        return $options;
    }

    private function findMatchingConversion(string $voucherCode, ?string $orderReference): ?AffiliateConversion
    {
        if (! $this->hasTableFor(AffiliateConversion::class)) {
            return null;
        }

        $query = AffiliateConversion::query()
            ->with(['affiliate', 'attribution.affiliate'])
            ->whereRaw('LOWER(voucher_code) = ?', [$voucherCode])
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($orderReference !== null) {
            return $query
                ->whereRaw('LOWER(external_reference) = ?', [$orderReference])
                ->first();
        }

        $matches = $query
            ->limit(2)
            ->get();

        if ($matches->count() !== 1) {
            return null;
        }

        return $matches->first();
    }

    private function findMatchingAttribution(string $voucherCode): ?AffiliateAttribution
    {
        if (! $this->hasTableFor(AffiliateAttribution::class)) {
            return null;
        }

        return AffiliateAttribution::query()
            ->with(['affiliate'])
            ->whereRaw('LOWER(voucher_code) = ?', [$voucherCode])
            ->orderByDesc('last_seen_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{
     *     affiliate_code: string|null,
     *     affiliate_name: string|null,
     *     source: string|null,
     *     medium: string|null,
     *     campaign: string|null,
     * }
     */
    private function contextFromConversion(AffiliateConversion $conversion): array
    {
        $attribution = $conversion->attribution;

        return [
            'affiliate_code' => $this->firstNonEmpty(
                $conversion->affiliate_code,
                $attribution?->affiliate_code,
            ),
            'affiliate_name' => $this->firstNonEmpty(
                $conversion->affiliate?->name,
                $attribution?->affiliate?->name,
            ),
            'source' => $attribution?->source,
            'medium' => $attribution?->medium,
            'campaign' => $attribution?->campaign,
        ];
    }

    /**
     * @return array{
     *     affiliate_code: string|null,
     *     affiliate_name: string|null,
     *     source: string|null,
     *     medium: string|null,
     *     campaign: string|null,
     * }
     */
    private function contextFromAttribution(AffiliateAttribution $attribution): array
    {
        return [
            'affiliate_code' => $attribution->affiliate_code,
            'affiliate_name' => $attribution->affiliate?->name,
            'source' => $attribution->source,
            'medium' => $attribution->medium,
            'campaign' => $attribution->campaign,
        ];
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = mb_trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function firstNonEmpty(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            $normalized = $this->normalizeString($value);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public function attributionOptions(string $column): array
    {
        if (! in_array($column, ['source', 'medium', 'campaign'], true) || ! $this->hasTableFor(AffiliateAttribution::class)) {
            return [];
        }

        /** @var array<string, string> $options */
        $options = AffiliateAttribution::query()
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column, $column)
            ->all();

        return $options;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function hasTableFor(string $modelClass): bool
    {
        if (! class_exists($modelClass)) {
            return false;
        }

        try {
            $model = new $modelClass;

            return $model instanceof Model && $model->getConnection()->getSchemaBuilder()->hasTable($model->getTable());
        } catch (Throwable) {
            return false;
        }
    }
}
