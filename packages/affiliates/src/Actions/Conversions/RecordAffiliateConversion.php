<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Conversions;

use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Services\AttributionModel;
use AIArmada\Affiliates\Services\CommissionCalculator;
use AIArmada\Affiliates\Services\Commissions\CommissionRuleEngine;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\ConversionStatus;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\Support\Webhooks\WebhookDispatcher;
use AIArmada\Cart\Cart;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Stringable;
use Lorisleiva\Actions\Concerns\AsAction;

final class RecordAffiliateConversion
{
    use AsAction;

    public function __construct(
        private readonly CommissionCalculator $commissionCalculator,
        private readonly CommissionRuleEngine $ruleEngine,
        private readonly Dispatcher $events,
        private readonly WebhookDispatcher $webhooks,
        private readonly AttributionModel $attributionModel,
        private readonly AllocateUplineCommissions $allocateUpline,
        private readonly ApplyConversionAccounting $accounting,
    ) {}

    public function handle(Cart $cart, array $payload = []): ?AffiliateConversionData
    {
        $metadata = $this->readCartMetadata($cart);

        if (! $metadata) {
            return null;
        }

        $affiliate = $this->resolveAffiliateFromMetadata($metadata);

        if (! $affiliate) {
            return null;
        }

        $attribution = $this->resolveAttributionFromMetadata($metadata);

        $subtotalMinor = $this->resolveMinorAmount($payload['subtotal'] ?? null, fn () => $cart->subtotal()->getAmount());
        $totalMinor = $this->resolveMinorAmount($payload['total'] ?? null, fn () => $cart->total()->getAmount());

        $commissionMinor = $this->resolveCommission(
            $affiliate,
            $subtotalMinor ?? $totalMinor ?? 0,
            $metadata,
            $payload,
        );

        $status = config('affiliates.commissions.default_status', PendingConversion::value());
        $statusEnum = ConversionStatus::fromString($status);
        $autoApprove = config('affiliates.commissions.auto_approve', false);

        $touches = $attribution?->touchpoints()->get() ?? collect();
        $weights = $this->attributionModel->distribute($touches);

        if ($weights === []) {
            $weights = [$affiliate->getKey() => 1.0];
        }

        $conversions = [];

        foreach ($weights as $affiliateId => $weight) {
            $weight = max(0, (float) $weight);
            $portionCommission = (int) round($commissionMinor * $weight);
            $portionRevenue = (int) round(($totalMinor ?? 0) * $weight);
            $beneficiary = $affiliateId === $affiliate->getKey()
                ? $affiliate
                : $this->findAffiliateById($affiliateId);

            $conversionMetadata = $this->buildConversionMetadata(
                $payload['metadata'] ?? [],
                $weight,
                $payload['subject_type'] ?? $attribution?->subject_type ?? ($metadata['subject_type'] ?? null),
                $payload['subject_title_snapshot'] ?? $attribution?->subject_title_snapshot ?? ($metadata['subject_title_snapshot'] ?? null),
            );

            if (isset($metadata['upline_levels'])) {
                $conversionMetadata['upline_levels'] = $metadata['upline_levels'];
            }

            $wasCreated = false;

            $conversion = AffiliateConversion::create([
                'affiliate_id' => $beneficiary?->getKey() ?? $affiliateId,
                'affiliate_code' => $beneficiary?->code ?? $affiliate->code,
                'affiliate_attribution_id' => $attribution?->getKey(),
                'subject_type' => $payload['subject_type'] ?? $attribution?->subject_type ?? ($metadata['subject_type'] ?? null),
                'subject_identifier' => $attribution?->subject_identifier ?? $cart->getIdentifier(),
                'subject_instance' => $attribution?->subject_instance ?? $cart->instance(),
                'subject_title_snapshot' => $payload['subject_title_snapshot'] ?? $attribution?->subject_title_snapshot ?? ($metadata['subject_title_snapshot'] ?? null),
                'voucher_code' => $metadata['voucher_code'] ?? null,
                'external_reference' => $payload['external_reference'] ?? null,
                'conversion_type' => $payload['conversion_type'] ?? 'purchase',
                'subtotal_minor' => $subtotalMinor ?? 0,
                'value_minor' => $portionRevenue,
                'commission_minor' => $portionCommission,
                'commission_currency' => $payload['commission_currency'] ?? $affiliate->currency,
                'status' => $autoApprove ? ApprovedConversion::class : $statusEnum::class,
                'channel' => $payload['channel'] ?? null,
                'metadata' => $conversionMetadata,
                'owner_type' => $beneficiary?->owner_type ?? $affiliate->owner_type,
                'owner_id' => $beneficiary?->owner_id ?? $affiliate->owner_id,
                'occurred_at' => $payload['occurred_at'] ?? now(),
                'approved_at' => $autoApprove ? now() : null,
            ]);

            $this->accounting->handle($conversion);

            $conversionData = AffiliateConversionData::fromModel($conversion);
            $conversions[] = $conversionData;

            if ($this->shouldDispatch('dispatch_conversion')) {
                $this->events?->dispatch(new AffiliateConversionRecorded($conversionData));
            }

            if ($this->shouldDispatch('dispatch_webhooks')) {
                $this->webhooks->dispatch('conversion', $conversionData->toArray());
            }
        }

        $this->allocateUpline->handle($conversions, $autoApprove, $statusEnum, $attribution?->getKey());

        return $conversions[0];
    }

    private function resolveCommission(Affiliate $affiliate, int $baseAmount, array $metadata, array $payload): int
    {
        if (isset($payload['commission'])) {
            return $this->resolveMinorAmount($payload['commission'], fn () => 0);
        }

        $voucherOverride = $metadata['commission_override'] ?? null;

        if (is_array($voucherOverride) && isset($voucherOverride['type'], $voucherOverride['value'])) {
            return $this->calculateVoucherCommission($voucherOverride, $baseAmount);
        }

        $programId = $metadata['affiliate_program_id'] ?? null;

        if ($programId) {
            $result = $this->ruleEngine->calculate($affiliate, $baseAmount, [
                'program_id' => $programId,
            ]);

            return $result->finalCommissionMinor;
        }

        return $this->commissionCalculator->calculate($affiliate, $baseAmount);
    }

    private function calculateVoucherCommission(array $override, int $baseAmount): int
    {
        $type = $override['type'];
        $value = (int) $override['value'];

        if ($type === CommissionType::Fixed->value) {
            return max(0, $value);
        }

        $scale = max(1, (int) config('affiliates.currency.percentage_scale', 100));

        return (int) max(0, round(($baseAmount * $value) / ($scale * 100)));
    }

    private function findAffiliateById(string $affiliateId): ?Affiliate
    {
        $query = Affiliate::query();

        if (config('affiliates.owner.enabled', false)) {
            $query->forOwner(includeGlobal: true);
        }

        return $query->find($affiliateId);
    }

    private function readCartMetadata(Cart $cart): ?array
    {
        $metadata = $cart->getMetadata($this->metadataKey());

        return is_array($metadata) ? $metadata : null;
    }

    private function resolveAffiliateFromMetadata(array $metadata): ?Affiliate
    {
        if (isset($metadata['affiliate_id'])) {
            $query = Affiliate::query();

            if (config('affiliates.owner.enabled', false)) {
                $query->forOwner(includeGlobal: true);
            }

            $affiliate = $query->find($metadata['affiliate_id']);

            if ($affiliate instanceof Affiliate) {
                return $affiliate;
            }
        }

        if (isset($metadata['affiliate_code'])) {
            $query = Affiliate::query()
                ->where('code', $metadata['affiliate_code']);

            if (config('affiliates.owner.enabled', false)) {
                $query->forOwner(includeGlobal: true);
            }

            return $query->first();
        }

        return null;
    }

    private function resolveAttributionFromMetadata(array $metadata): ?AffiliateAttribution
    {
        if (! isset($metadata['attribution_id'])) {
            return null;
        }

        $query = AffiliateAttribution::query()
            ->whereKey($metadata['attribution_id']);

        if (config('affiliates.owner.enabled', false)) {
            $query->forOwner(includeGlobal: true);
        }

        return $query->first();
    }

    private function resolveMinorAmount(mixed $value, callable $fallback): ?int
    {
        if ($value === null) {
            $resolved = $fallback();

            if ($resolved === null) {
                return null;
            }

            return (int) ($resolved instanceof Stringable ? (string) $resolved : $resolved);
        }

        if ($value instanceof Stringable) {
            return (int) (string) $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function buildConversionMetadata(array $metadata, float $weight, ?string $subjectType, ?string $subjectTitleSnapshot): array
    {
        $metadata['weight'] = $weight;

        if ($subjectType && ! isset($metadata['subject_type'])) {
            $metadata['subject_type'] = $subjectType;
        }

        if ($subjectTitleSnapshot && ! isset($metadata['subject_title_snapshot'])) {
            $metadata['subject_title_snapshot'] = $subjectTitleSnapshot;
        }

        return $metadata;
    }

    private function metadataKey(): string
    {
        return (string) config('affiliates.cart.metadata_key', 'affiliate');
    }

    private function shouldDispatch(string $flag): bool
    {
        return (bool) config("affiliates.events.{$flag}", true);
    }
}
