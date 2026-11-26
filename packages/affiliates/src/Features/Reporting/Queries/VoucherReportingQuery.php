<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Reporting\Queries;

use AIArmada\Affiliates\Features\Attribution\Api\AttributionReportingReader;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionReportingReader;
use AIArmada\Affiliates\Features\Conversions\Api\VoucherConversionRecord;
use AIArmada\Affiliates\Features\Reporting\Api\VoucherReportingContext;
use AIArmada\Affiliates\Features\Reporting\Api\VoucherReportingReader;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateReportingReader;

final readonly class VoucherReportingQuery implements VoucherReportingReader
{
    public function __construct(private AttributionReportingReader $attributions, private ConversionReportingReader $conversions, private AffiliateReportingReader $affiliates) {}

    public function available(): bool
    {
        return $this->conversions->available() || $this->attributions->available();
    }

    public function resolve(string $voucherCode, ?string $orderReference): ?VoucherReportingContext
    {
        $voucherCode = mb_strtolower($voucherCode);
        $orderReference = $orderReference === null ? null : mb_strtolower($orderReference);
        $conversion = $this->conversions->voucher($voucherCode, $orderReference);
        $context = $conversion === null ? null : $this->contextFromConversion($conversion);

        if ($context === null || ! array_filter($context, fn (mixed $value): bool => $this->normalizeString($value) !== null)) {
            $context = $this->attributions->latestForVoucher($voucherCode)?->toArray();
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
        return $this->affiliates->available()
            ? $this->affiliates->codeOptions()
            : $this->attributions->options('affiliate_code');
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
    private function contextFromConversion(VoucherConversionRecord $conversion): array
    {
        $attribution = $conversion->attributionId === null ? null : $this->attributions->findForConversion(
            $conversion->attributionId,
            $conversion->affiliateId,
            $conversion->ownerType,
            $conversion->ownerId,
            $conversion->connectionIdentity,
        );

        return [
            'affiliate_code' => $this->firstNonEmpty(
                $conversion->affiliateCode,
                $attribution?->affiliateCode,
            ),
            'affiliate_name' => $this->firstNonEmpty(
                $conversion->affiliateName,
                $attribution?->affiliateName,
            ),
            'source' => $attribution?->source,
            'medium' => $attribution?->medium,
            'campaign' => $attribution?->campaign,
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
        return in_array($column, ['source', 'medium', 'campaign'], true) ? $this->attributions->options($column) : [];
    }

}
