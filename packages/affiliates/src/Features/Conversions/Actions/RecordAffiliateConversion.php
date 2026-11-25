<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Actions;

use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Data\AttributionTouch;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Features\Conversions\Api\CartConversionRecording;
use AIArmada\Affiliates\Features\Fraud\Api\FraudApi;
use AIArmada\Affiliates\Features\Webhooks\Api\WebhookApi;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use AIArmada\Affiliates\Features\Attribution\Api\AttributionDistribution;
use AIArmada\Affiliates\Features\Commissions\Api\CommissionPricing;
use AIArmada\Affiliates\Features\Commissions\Api\CommissionRuleCalculation;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\ConversionStatus;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\States\RejectedConversion;
use AIArmada\Cart\Cart;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Stringable;
use InvalidArgumentException;
use LogicException;

final readonly class RecordAffiliateConversion implements CartConversionRecording
{
    public function __construct(
        private CommissionPricing $commissionPricing,
        private CommissionRuleCalculation $ruleEngine,
        private Dispatcher $events,
        private WebhookApi $webhooks,
        private AttributionDistribution $attributionModel,
        private AllocateUplineCommissions $allocateUpline,
        private ApplyConversionAccounting $accounting,
        private FraudApi $fraud,
    ) {}

    public function record(Cart $cart, array $payload = []): ?AffiliateConversionData
    {
        return (new AffiliateConversion)->getConnection()->transaction(fn (): ?AffiliateConversionData => $this->recordConversion($cart, $payload));
    }

    public function recordFrozen(Cart $cart, string $attributionId, array $payload = []): ?AffiliateConversionData
    {
        $attribution = AffiliateAttribution::query()->forOwner()->findOrFail($attributionId);

        return $this->recordCommercialForFrozenAttribution($cart, $attribution, $payload);
    }

    public function attributionTouchFor(Cart $cart): ?CarbonImmutable
    {
        $seen = $this->resolveAttributionFor($cart)?->last_referred_at;

        return $seen === null ? null : CarbonImmutable::instance($seen);
    }

    /**
     * Record against an attribution frozen before an external checkout handoff.
     *
     * The frozen attribution is authoritative even if its cookie window or
     * tracked link changes before payment confirmation. A frozen commercial
     * attribution is one affiliate decision, so touchpoint weights cannot
     * fan the conversion out to other affiliates.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordCommercialForFrozenAttribution(
        Cart $cart,
        AffiliateAttribution $attribution,
        array $payload = [],
    ): ?AffiliateConversionData {
        $affiliate = $attribution->affiliate;

        if (! $affiliate instanceof Affiliate || ! $affiliate->isCommercialPurpose()) {
            throw new InvalidArgumentException('Frozen commission attribution must belong to a commercial-purpose affiliate.');
        }

        if (isset($payload['affiliate_id']) && (string) $payload['affiliate_id'] !== (string) $affiliate->getKey()) {
            throw new InvalidArgumentException('Frozen commission attribution does not match the requested affiliate.');
        }

        foreach ([
            'affiliate_code' => $attribution->affiliate_code ?? $affiliate->code,
            'affiliate_link_id' => $attribution->affiliate_link_id,
            'affiliate_program_id' => $attribution->affiliate_program_id,
        ] as $key => $frozenValue) {
            if (array_key_exists($key, $payload)
                && ($payload[$key] === null ? null : (string) $payload[$key]) !== ($frozenValue === null ? null : (string) $frozenValue)) {
                throw new InvalidArgumentException("Frozen commission attribution does not match requested [{$key}].");
            }
        }

        $payload['affiliate_attribution_id'] = (string) $attribution->getKey();

        return (new AffiliateConversion)->getConnection()->transaction(fn (): ?AffiliateConversionData => $this->recordConversion($cart, $payload, $attribution));
    }

    /**
     * Resolve the active attribution for a cart without recording.
     *
     * Lets order listeners compare touch times before deciding whether
     * this conversion should exist at all (cross-system winner rule).
     */
    public function resolveAttributionFor(Cart $cart): ?AffiliateAttribution
    {
        return $this->resolveAttribution($cart, []);
    }

    private function recordConversion(Cart $cart, array $payload = [], ?AffiliateAttribution $frozenAttribution = null): ?AffiliateConversionData
    {
        $attribution = $frozenAttribution ?? $this->resolveAttribution($cart, $payload);
        $affiliate = $this->resolveAffiliate($payload, $attribution);

        if (! $affiliate) {
            return null;
        }

        $subtotalMinor = $this->resolveMinorAmount($payload['subtotal'] ?? null, fn () => $cart->subtotal()->getAmount());
        $totalMinor = $this->resolveMinorAmount($payload['total'] ?? null, fn () => $cart->total()->getAmount());

        // Pricing can consume a limited-use promotion. Resolve it only when
        // there is a new conversion leg to post, after idempotency is checked.
        $commissionMinor = null;

        $status = config('affiliates.commissions.default_status', PendingConversion::value());
        $statusEnum = ConversionStatus::fromString($status);
        $autoApprove = config('affiliates.commissions.auto_approve', false);

        $weights = $frozenAttribution instanceof AffiliateAttribution
            ? [$affiliate->getKey() => 1.0]
            : $this->attributionModel->distribute(
                $attribution?->touchpoints()
                    ->get(['affiliate_id', 'touched_at'])
                    ->map(static fn (AffiliateTouchpoint $touch): AttributionTouch => new AttributionTouch(
                        (string) $touch->affiliate_id,
                        $touch->touched_at?->toImmutable(),
                    ))
                    ->values()
                    ->all() ?? [],
            );

        if ($weights === []) {
            $weights = [$affiliate->getKey() => 1.0];
        }

        $conversionType = (string) ($payload['conversion_type'] ?? 'purchase');
        $externalReference = $payload['external_reference'] ?? null;
        $channel = $payload['channel'] ?? $attribution?->channel;
        $conversions = [];
        $firstConversion = null;

        // Lock every credited identity in a stable order. Deletion and
        // concurrent recording must serialize against the same affiliate rows.
        $connection = (new AffiliateConversion)->getConnection();

        if ($connection !== $affiliate->getConnection()) {
            throw new LogicException('Conversion recording requires a shared affiliate database connection.');
        }

        $ids = array_values(array_unique([(string) $affiliate->getKey(), ...array_map('strval', array_keys($weights))]));
        $locked = Affiliate::query()->forOwner(includeGlobal: true)->whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $affiliate = $locked->get((string) $affiliate->getKey());

        if (! $affiliate instanceof Affiliate || $locked->count() !== count($ids)) {
            throw new InvalidArgumentException('A conversion beneficiary is missing or outside the current owner scope.');
        }

        $explicitIdempotencyKey = $payload['idempotency_key'] ?? null;

        if ($explicitIdempotencyKey !== null
            && (! is_string($explicitIdempotencyKey)
                || mb_trim($explicitIdempotencyKey) === ''
                || mb_strlen($explicitIdempotencyKey) > 64)) {
            throw new InvalidArgumentException('Explicit conversion idempotency keys must be non-empty strings up to 64 characters.');
        }


        foreach ($weights as $affiliateId => $weight) {
            $weight = max(0, (float) $weight);
            $portionRevenue = (int) round(($totalMinor ?? 0) * $weight);
            $beneficiary = $locked->get((string) $affiliateId);

            if (! $beneficiary instanceof Affiliate) {
                throw new LogicException('A locked conversion beneficiary is unavailable.');
            }

            $affiliateId = (string) $beneficiary->getKey();
            $ownerType = $beneficiary->owner_type;
            $ownerId = $beneficiary->owner_id;

            $idempotencyKey = is_string($explicitIdempotencyKey)
                ? hash('sha256', implode('|', [
                    'affiliate-conversion',
                    $ownerType ?? 'global',
                    $ownerId ?? 'global',
                    $affiliateId,
                    mb_trim($explicitIdempotencyKey),
                ]))
                : $this->resolveIdempotencyKey(
                    $externalReference,
                    $affiliateId,
                    $conversionType,
                    $channel,
                    $ownerType,
                    $ownerId,
                    $cart,
                    $subtotalMinor,
                    $totalMinor,
                );

            $existing = AffiliateConversion::query()->forOwner(includeGlobal: true)
                ->where('idempotency_key', $idempotencyKey)->first();

            if ($existing instanceof AffiliateConversion) {
                $existingData = $existing->toData();
                $firstConversion ??= $existingData;

                if ($this->shouldDispatch('dispatch_conversion')) {
                    $this->events->dispatch(new AffiliateConversionRecorded($existingData));
                }

                continue;
            }

            $commissionMinor ??= $this->resolveCommission(
                $affiliate,
                $subtotalMinor ?? $totalMinor ?? 0,
                $payload,
                $attribution,
            );
            $portionCommission = $beneficiary->isCommercialPurpose()
                ? (int) round($commissionMinor * $weight)
                : 0;
            $conversionMetadata = $this->buildConversionMetadata(
                $payload['metadata'] ?? [],
                $weight,
                $payload['subject_type'] ?? $attribution?->subject_type,
                $payload['subject_title_snapshot'] ?? $attribution?->subject_title_snapshot,
            );

            $conversionAttributes = [
                'affiliate_id' => $affiliateId,
                'affiliate_code' => $beneficiary->code,
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
                'external_reference' => $externalReference,
                'conversion_type' => $conversionType,
                'subtotal_minor' => $subtotalMinor ?? 0,
                'value_minor' => $portionRevenue,
                'commission_minor' => $portionCommission,
                'commission_currency' => mb_strtoupper((string) ($payload['commission_currency'] ?? $affiliate->currency ?? config('affiliates.currency.default', 'MYR'))),
                'status' => $autoApprove ? ApprovedConversion::class : $statusEnum::class,
                'channel' => $channel,
                'origin' => $payload['origin'] ?? $attribution?->origin,
                'source_ref' => $payload['source_ref'] ?? null,
                'sharer_user_id' => $payload['sharer_user_id'] ?? $attribution?->sharer_user_id,
                'actor_user_id' => $payload['actor_user_id'] ?? null,
                'metadata' => $conversionMetadata,
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'occurred_at' => $payload['occurred_at'] ?? CarbonImmutable::now(),
                'approved_at' => $autoApprove ? CarbonImmutable::now() : null,
            ];

            if (is_string($explicitIdempotencyKey)) {
                $conversionAttributes['metadata']['caller_idempotency_key'] = mb_trim($explicitIdempotencyKey);
            }

            $conversionAttributes['idempotency_key'] = $idempotencyKey;
            $conversion = $this->createIdempotent($conversionAttributes);

            if (! $conversion->wasRecentlyCreated) {
                // Retry-safe delivery: the ledger row already exists but
                // a prior attempt may never have dispatched (see the
                // outcome path). Webhooks stay first-write-only: hosts
                // must not receive duplicate external calls on retry.
                $existingData = $conversion->toData();
                $firstConversion ??= $existingData;

                if ($this->shouldDispatch('dispatch_conversion')) {
                    $this->events?->dispatch(new AffiliateConversionRecorded($existingData));
                }

                continue;
            }

            if (! $this->fraud->analyzeConversion((string) $conversion->getKey())->allowed) {
                $conversion->update(['status' => RejectedConversion::class]);
            }

            $this->accounting->handle($conversion);

            if ($conversion->status->equals(RejectedConversion::class)) {
                $firstConversion ??= $conversion->toData();

                continue;
            }

            $firstConversion ??= $conversion->toData();

            $conversionData = $conversion->toData();
            $conversions[] = $conversionData;

            if ($this->shouldDispatch('dispatch_conversion')) {
                $this->events?->dispatch(new AffiliateConversionRecorded($conversionData));
            }

            if ($this->shouldDispatch('dispatch_webhooks')) {
                $this->webhooks->dispatch('conversion', $conversionData->toArray());
            }
        }

        if ($conversions !== []) {
            $this->allocateUpline->handle($conversions, $autoApprove, $statusEnum, $attribution?->getKey());
        }

        return $firstConversion;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createIdempotent(array $attributes): AffiliateConversion
    {
        $existing = AffiliateConversion::query()->forOwner(includeGlobal: true)
            ->where('idempotency_key', $attributes['idempotency_key'])
            ->first();

        if ($existing) {
            $existing->wasRecentlyCreated = false;

            return $existing;
        }

        try {
            $conversion = (new AffiliateConversion)->getConnection()->transaction(function () use ($attributes): AffiliateConversion {
                $model = new AffiliateConversion(Arr::except($attributes, ['owner_type', 'owner_id']));
                $model->forceFill(Arr::only($attributes, ['owner_type', 'owner_id']));
                $model->save();

                return $model;
            }, attempts: 1);
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            $existing = AffiliateConversion::query()->forOwner(includeGlobal: true)
                ->where('idempotency_key', $attributes['idempotency_key'])
                ->firstOrFail();
            $existing->wasRecentlyCreated = false;

            return $existing;
        }

        $conversion->wasRecentlyCreated = true;

        return $conversion;
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }

    private function resolveCommission(
        Affiliate $affiliate,
        int $baseAmount,
        array $payload,
        ?AffiliateAttribution $attribution,
    ): int {
        if (! $affiliate->isCommercialPurpose()) {
            return 0;
        }

        if (isset($payload['commission'])) {
            return $this->commissionPricing->clamp($this->resolveMinorAmount($payload['commission'], fn () => 0) ?? 0);
        }

        $voucherOverride = $payload['commission_override'] ?? $attribution?->commission_override;

        if (is_array($voucherOverride) && isset($voucherOverride['type'], $voucherOverride['value'])) {
            return $this->commissionPricing->clamp($this->calculateVoucherCommission($voucherOverride, $baseAmount));
        }

        $programId = $payload['affiliate_program_id'] ?? $attribution?->affiliate_program_id;

        if ($programId) {
            $result = $this->ruleEngine->calculate((string) $affiliate->getKey(), $baseAmount, [
                'program_id' => $programId,
            ]);

            return $result->finalCommissionMinor;
        }

        return $this->commissionPricing->calculateBase($affiliate->commission_type->value, $affiliate->commission_rate, $baseAmount);
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
            'subject_title_snapshot', 'voucher_code', 'channel', 'origin', 'source_ref',
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

    private function resolveIdempotencyKey(
        mixed $externalReference,
        string $affiliateId,
        string $conversionType,
        mixed $channel,
        ?string $ownerType,
        ?string $ownerId,
        Cart $cart,
        ?int $subtotalMinor,
        ?int $totalMinor,
    ): string {
        if (is_string($externalReference) && mb_trim($externalReference) !== '') {
            $seed = 'ref:' . $externalReference;
        } else {
            $seed = implode(':', [
                'cart',
                $cart->getIdentifier(),
                $cart->instance(),
                $subtotalMinor ?? 0,
                $totalMinor ?? 0,
            ]);
        }

        return hash('sha256', implode('|', [
            $ownerType ?? '',
            $ownerId ?? '',
            $affiliateId,
            $conversionType,
            is_string($channel) ? $channel : '',
            $seed,
        ]));
    }
}
