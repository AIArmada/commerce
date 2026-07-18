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
use Illuminate\Support\Arr;
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
        $attribution = $this->resolveAttribution($cart, $payload);
        $affiliate = $this->resolveAffiliate($payload, $attribution);

        if (! $affiliate) {
            return null;
        }

        $subtotalMinor = $this->resolveMinorAmount($payload['subtotal'] ?? null, fn () => $cart->subtotal()->getAmount());
        $totalMinor = $this->resolveMinorAmount($payload['total'] ?? null, fn () => $cart->total()->getAmount());

        $commissionMinor = $this->resolveCommission(
            $affiliate,
            $subtotalMinor ?? $totalMinor ?? 0,
            $payload,
            $attribution,
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
                $payload['subject_type'] ?? $attribution?->subject_type,
                $payload['subject_title_snapshot'] ?? $attribution?->subject_title_snapshot,
            );

            $conversion = AffiliateConversion::create([
                'affiliate_id' => $beneficiary?->getKey() ?? $affiliateId,
                'affiliate_code' => $beneficiary?->code ?? $affiliate->code,
                'affiliate_attribution_id' => $attribution?->getKey(),
                'affiliate_link_id' => $payload['affiliate_link_id'] ?? $attribution?->affiliate_link_id,
                'affiliate_program_id' => $payload['affiliate_program_id'] ?? $attribution?->affiliate_program_id,
                'subject_type' => $payload['subject_type'] ?? $attribution?->subject_type,
                'subject_key' => $payload['subject_key'] ?? $attribution?->subject_key ?? $cart->getIdentifier(),
                'subject_id' => $payload['subject_id'] ?? $attribution?->subject_id,
                'subject_instance' => $payload['subject_instance'] ?? $attribution?->subject_instance ?? $cart->instance(),
                'subject_title_snapshot' => $payload['subject_title_snapshot'] ?? $attribution?->subject_title_snapshot,
                'voucher_code' => $payload['voucher_code'] ?? $attribution?->voucher_code,
                'commission_override' => $payload['commission_override'] ?? $attribution?->commission_override,
                'upline_levels' => $payload['upline_levels'] ?? $attribution?->upline_levels,
                'external_reference' => $payload['external_reference'] ?? null,
                'conversion_type' => $payload['conversion_type'] ?? 'purchase',
                'subtotal_minor' => $subtotalMinor ?? 0,
                'value_minor' => $portionRevenue,
                'commission_minor' => $portionCommission,
                'commission_currency' => $payload['commission_currency'] ?? $affiliate->currency,
                'status' => $autoApprove ? ApprovedConversion::class : $statusEnum::class,
                'channel' => $payload['channel'] ?? $attribution?->channel,
                'origin' => $payload['origin'] ?? $attribution?->origin,
                'sharer_user_id' => $payload['sharer_user_id'] ?? $attribution?->sharer_user_id,
                'actor_user_id' => $payload['actor_user_id'] ?? null,
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

    private function resolveCommission(
        Affiliate $affiliate,
        int $baseAmount,
        array $payload,
        ?AffiliateAttribution $attribution,
    ): int {
        if (isset($payload['commission'])) {
            return $this->resolveMinorAmount($payload['commission'], fn () => 0);
        }

        $voucherOverride = $payload['commission_override'] ?? $attribution?->commission_override;

        if (is_array($voucherOverride) && isset($voucherOverride['type'], $voucherOverride['value'])) {
            return $this->calculateVoucherCommission($voucherOverride, $baseAmount);
        }

        $programId = $payload['affiliate_program_id'] ?? $attribution?->affiliate_program_id;

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

    private function resolveAttribution(Cart $cart, array $payload): ?AffiliateAttribution
    {
        $attributionId = $payload['affiliate_attribution_id'] ?? null;

        $query = AffiliateAttribution::query()->active();

        if ($attributionId !== null) {
            $query->whereKey($attributionId);
        } else {
            $query
                ->where('cart_identifier', $cart->getIdentifier())
                ->where('cart_instance', $cart->instance())
                ->latest('last_seen_at')
                ->latest('id');
        }

        if (config('affiliates.owner.enabled', false)) {
            $query->forOwner(includeGlobal: true);
        }

        return $query->first();
    }

    private function resolveAffiliate(array $payload, ?AffiliateAttribution $attribution): ?Affiliate
    {
        if (isset($payload['affiliate_id'])) {
            $query = Affiliate::query();

            if (config('affiliates.owner.enabled', false)) {
                $query->forOwner(includeGlobal: true);
            }

            $affiliate = $query->find($payload['affiliate_id']);

            if ($affiliate instanceof Affiliate) {
                return $affiliate;
            }
        }

        if (isset($payload['affiliate_code'])) {
            $query = Affiliate::query()
                ->where('code', $payload['affiliate_code']);

            if (config('affiliates.owner.enabled', false)) {
                $query->forOwner(includeGlobal: true);
            }

            return $query->first();
        }

        return $attribution?->affiliate;
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
        $metadata = Arr::except($metadata, [
            'affiliate_id', 'affiliate_code', 'affiliate_attribution_id', 'affiliate_link_id',
            'subject_type', 'subject_key', 'subject_id', 'subject_instance',
            'subject_title_snapshot', 'voucher_code', 'channel', 'origin',
            'sharer_user_id', 'actor_user_id', 'external_reference', 'conversion_type',
            'affiliate_program_id', 'commission_override', 'upline_levels', 'program_id', 'cart_identifier', 'cart_instance',
        ]);
        $metadata['weight'] = $weight;

        return $metadata;
    }

    private function shouldDispatch(string $flag): bool
    {
        return (bool) config("affiliates.events.{$flag}", true);
    }
}
