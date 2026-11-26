<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Actions;

use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Data\AffiliateOutcomeDisposition;
use AIArmada\Affiliates\Data\AffiliateOutcomeResult;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Features\Attribution\Api\AttributionConversionReader;
use AIArmada\Affiliates\Features\Attribution\Api\AttributionConversionRecord;
use AIArmada\Affiliates\Features\Attribution\Api\TrackingLinkReader;
use AIArmada\Affiliates\Features\Commissions\Api\CommissionPricing;
use AIArmada\Affiliates\Features\Conversions\Api\OutcomePosting;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateBalance;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\States\RejectedConversion;
use AIArmada\Affiliates\Features\Fraud\Api\FraudApi;
use AIArmada\Affiliates\Features\Fraud\Api\FraudSignalMaintenance;
use AIArmada\Affiliates\Features\Identity\Api\ConversionBeneficiary;
use AIArmada\Affiliates\Features\Identity\Api\ConversionBeneficiaryReader;
use AIArmada\Affiliates\Features\Programs\Api\ProgramReferenceReader;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use LogicException;

/**
 * Record an attributed non-cart conversion.
 *
 * Cart conversions use RecordAffiliateConversion, while referral, signup, and
 * engagement flows need the same conversion ledger without a cart aggregate.
 *
 * Identity: the caller supplies a bounded caller-owned namespaced business
 * key (for example `share-outcome:signup:user:{uuid}`). The package hashes the
 * exact validated identity (`sha256(businessKey)`) for the unique
 * `idempotency_key`, independent of affiliate/link. Lookup, insertion, and
 * collision recovery all use that same hash. Replays return the stored row
 * untouched — the original winning affiliate/owner/attribution is preserved.
 *
 * Transaction: one same-connection transaction covers insertion (via a
 * savepoint for PostgreSQL-safe unique recovery), persisted fraud-rule
 * signal writes on the real inserted conversion, rejection marking, and
 * accounting. Replays perform zero writes and rerun neither fraud nor
 * accounting. Events dispatch after commit only, gated by the payload
 * `dispatch_event` flag. Caller-level retry is safe: re-entry returns
 * `Replayed` via the idempotency preflight.
 */
final readonly class RecordAffiliateOutcome implements OutcomePosting
{
    public function __construct(
        private Dispatcher $events,
        private ApplyConversionAccounting $accounting,
        private FraudApi $fraud,
        private AttributionConversionReader $attributions,
    ) {}

    public function post(?string $attributionId, string $conversionType, string $businessKey, array $payload = [], ?string $affiliateId = null, array $provenance = []): AffiliateOutcomeResult
    {
        $conversionType = mb_trim($conversionType);
        $businessKey = mb_trim($businessKey);

        if ($conversionType === '' || mb_strlen($conversionType) > 64) {
            throw new InvalidArgumentException('Conversion type must be a non-empty string of at most 64 characters.');
        }

        if (mb_strtolower($conversionType) === RecordAffiliateAward::CONVERSION_TYPE) {
            throw new InvalidArgumentException('Fixed monetary awards must be recorded through RecordAffiliateAward.');
        }

        if ($businessKey === '' || mb_strlen($businessKey) > 191 || str_contains($businessKey, "\0")) {
            throw new InvalidArgumentException('Business key must be a non-empty caller-owned namespaced identity of at most 191 characters.');
        }

        $connection = (new AffiliateConversion)->getConnection();
        $attribution = $attributionId === null
            ? null
            : $this->attributions->forOutcome($attributionId, spl_object_id($connection));

        if ($attribution !== null) {
            if ($affiliateId !== null && $affiliateId !== $attribution->affiliateId) {
                throw new InvalidArgumentException('Outcome affiliate must match the persisted attribution.');
            }

            $affiliateId = $attribution->affiliateId;
        }

        $affiliate = $affiliateId === null ? null : app(ConversionBeneficiaryReader::class)->find($affiliateId, spl_object_id($connection));

        if ($affiliate === null) {
            return new AffiliateOutcomeResult(AffiliateOutcomeDisposition::NoAffiliate, null);
        }

        $this->assertSameConnection();

        if ($attribution instanceof AttributionConversionRecord) {
            $this->assertSameOwner($attribution, $affiliate);
            $this->assertAttributionReferences($attribution, $affiliate, $payload, $provenance);
        } else {
            $this->assertStandaloneReferences($affiliate, $payload, $provenance);
        }

        $subjectKey = $this->mergedValue($provenance, $payload, $attribution, 'subject_key');
        $origin = $this->mergedValue($provenance, $payload, $attribution, 'origin');

        if ($attribution === null && ($subjectKey === null || $subjectKey === '')) {
            throw new InvalidArgumentException('Attribution-free outcomes require an explicit subject_key.');
        }

        if ($attribution === null && ($origin === null || $origin === '')) {
            throw new InvalidArgumentException('Attribution-free outcomes require an explicit origin.');
        }

        $idempotencyKey = hash('sha256', $businessKey);
        $dispatchEvent = (bool) Arr::get($payload, 'dispatch_event', true);

        $metadata = Arr::get($payload, 'metadata', []);

        if (! is_array($metadata)) {
            $metadata = [];
        }

        $metadata = Arr::except($metadata, [
            'affiliate_id', 'affiliate_code', 'affiliate_attribution_id',
            'subject_type', 'subject_key', 'subject_id', 'subject_instance',
            'subject_title_snapshot', 'origin', 'voucher_code', 'program_id',
        ]);

        $externalReference = Arr::get($payload, 'external_reference');

        if (is_string($externalReference)) {
            $externalReference = mb_trim($externalReference);
            $externalReference = $externalReference === '' ? null : mb_substr($externalReference, 0, 120);
        } else {
            $externalReference = null;
        }

        if ($externalReference === null) {
            $externalReference = mb_substr($businessKey, 0, 120);
        }

        $linkId = $this->mergedValue($provenance, $payload, $attribution, 'affiliate_link_id');
        $programId = $this->mergedValue($provenance, $payload, $attribution, 'affiliate_program_id');
        $sharerUserId = $this->mergedValue($provenance, $payload, $attribution, 'sharer_user_id');
        $actorUserId = $this->mergedValue($provenance, $payload, $attribution, 'actor_user_id');

        /** @var array{disposition: AffiliateOutcomeDisposition, conversion_id: string} $committed */
        $committed = $connection->transaction(function () use (
            $connection,
            $affiliate,
            $attribution,
            $conversionType,
            $idempotencyKey,
            $externalReference,
            $metadata,
            $payload,
            $subjectKey,
            $origin,
            $linkId,
            $programId,
            $sharerUserId,
            $actorUserId
        ): array {
            $locked = app(ConversionBeneficiaryReader::class)->lock([$affiliate->reference->id], spl_object_id($connection));
            $affiliate = $locked[$affiliate->reference->id];

            if ($attribution !== null) {
                $this->assertSameOwner($attribution, $affiliate);
            }

            // Fresh instance per attempt: reusing a saved model across
            // outer-transaction retries leaves exists=true after rollback,
            // turning the replayed save into a silent no-op (phantom).
            $conversion = new AffiliateConversion([
                'idempotency_key' => $idempotencyKey,
                'affiliate_id' => $affiliate->reference->id,
                'affiliate_attribution_id' => $attribution?->id,
                'affiliate_program_id' => $programId,
                'affiliate_code' => $affiliate->code,
                'subject_type' => Arr::get($payload, 'subject_type', $attribution?->subjectType),
                'subject_key' => $subjectKey !== '' ? $subjectKey : null,
                'subject_instance' => Arr::get($payload, 'subject_instance', $attribution?->subjectInstance),
                'subject_title_snapshot' => Arr::get($payload, 'subject_title_snapshot', $attribution?->subjectTitleSnapshot),
                'subject_id' => Arr::get($payload, 'subject_id', $attribution?->subjectId),
                'affiliate_link_id' => $linkId,
                'external_reference' => $externalReference,
                'commission_override' => Arr::get($payload, 'commission_override', $attribution?->commissionOverride),
                'upline_levels' => Arr::get($payload, 'upline_levels', $attribution?->uplineLevels),
                'conversion_type' => $conversionType,
                'subtotal_minor' => (int) Arr::get($payload, 'subtotal_minor', 0),
                'value_minor' => (int) Arr::get($payload, 'value_minor', 0),
                'commission_minor' => $affiliate->commercial
                    ? app(CommissionPricing::class)->clamp((int) Arr::get($payload, 'commission_minor', 0))
                    : 0,
                'commission_currency' => (string) Arr::get($payload, 'commission_currency', config('affiliates.currency.default', 'MYR')),
                'status' => Arr::get($payload, 'status', config('affiliates.commissions.default_status', 'pending')),
                'channel' => Arr::get($payload, 'channel'),
                'origin' => $origin !== '' ? $origin : null,
                'sharer_user_id' => $sharerUserId,
                'actor_user_id' => $actorUserId,
                'metadata' => $metadata,
                'occurred_at' => Arr::get($payload, 'occurred_at', CarbonImmutable::now()),
                'approved_at' => Arr::get($payload, 'approved_at'),
            ]);
            $conversion->forceFill([
                'owner_type' => $affiliate->reference->ownerType,
                'owner_id' => $affiliate->reference->ownerId,
            ]);

            $existing = AffiliateConversion::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof AffiliateConversion) {
                return [
                    'disposition' => AffiliateOutcomeDisposition::Replayed,
                    'conversion_id' => (string) $existing->getKey(),
                ];
            }

            try {
                $connection->transaction(static fn (): mixed => $conversion->save(), 1);
            } catch (QueryException $exception) {
                if (! $this->isUniqueViolation($exception)) {
                    throw $exception;
                }

                $raced = AffiliateConversion::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if (! $raced instanceof AffiliateConversion) {
                    throw $exception;
                }

                return [
                    'disposition' => AffiliateOutcomeDisposition::Replayed,
                    'conversion_id' => (string) $raced->getKey(),
                ];
            }

            $fraudVerdict = $this->fraud->analyzeConversion((string) $conversion->getKey());
            $rejected = ! $fraudVerdict->allowed;

            if ($rejected) {
                $conversion->update(['status' => RejectedConversion::class]);
                $conversion->refresh();
            }

            $this->accounting->handle($conversion);

            return [
                'disposition' => $rejected ? AffiliateOutcomeDisposition::Rejected : AffiliateOutcomeDisposition::Created,
                'conversion_id' => (string) $conversion->getKey(),
            ];
            // Concurrent same-key inserts deadlock on gap locks under
            // InnoDB; the retry replays the committed winner row.
        }, 3);

        $stored = AffiliateConversion::query()->whereKey($committed['conversion_id'])->firstOrFail();
        $result = new AffiliateOutcomeResult(
            $committed['disposition'],
            $stored->toData(),
        );

        if ($dispatchEvent) {
            $conversionData = $result->conversion;
            $connection->afterCommit(function () use ($conversionData): void {
                if ($conversionData instanceof AffiliateConversionData) {
                    $this->events->dispatch(new AffiliateConversionRecorded($conversionData));
                }
            });
        }

        return $result;
    }

    private function assertSameOwner(AttributionConversionRecord $attribution, ConversionBeneficiary $affiliate): void
    {
        if ($attribution->ownerType !== $affiliate->reference->ownerType
            || (string) $attribution->ownerId !== (string) $affiliate->reference->ownerId) {
            throw new LogicException('Affiliate outcome recording requires an attribution and affiliate with the same owner.');
        }
    }

    /**
     * Precedence: explicit provenance wins, then payload, then attribution-derived
     * defaults. Existing payload-over-attribution behavior is preserved.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $provenance
     */
    private function mergedValue(array $provenance, array $payload, ?AttributionConversionRecord $attribution, string $key): mixed
    {
        if (array_key_exists($key, $provenance) && $provenance[$key] !== null) {
            return $provenance[$key];
        }

        if (array_key_exists($key, $payload) && $payload[$key] !== null) {
            return $payload[$key];
        }

        return $attribution?->provenance($key);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $provenance
     */
    private function assertAttributionReferences(
        AttributionConversionRecord $attribution,
        ConversionBeneficiary $affiliate,
        array $payload,
        array $provenance = [],
    ): void {
        foreach (['affiliate_link_id', 'affiliate_program_id'] as $key) {
            $reference = $this->mergedValue($provenance, $payload, $attribution, $key);

            if ((! is_string($reference) && ! is_int($reference) && $reference !== null)
                || (string) $reference !== (string) $attribution->provenance($key)) {
                throw new InvalidArgumentException(sprintf('Outcome reference [%s] must match the persisted attribution.', $key));
            }
        }

        if ($attribution->affiliateLinkId !== null
            && ! app(TrackingLinkReader::class)->belongsTo($attribution->affiliateLinkId, (string) $affiliate->reference->id)) {
            throw new InvalidArgumentException('The attribution link does not belong to its affiliate.');
        }

        if ($attribution->affiliateProgramId !== null
            && ! app(ProgramReferenceReader::class)->existsForOwner(
                $attribution->affiliateProgramId,
                $affiliate->reference->ownerType,
                $affiliate->reference->ownerId,
                spl_object_id((new AffiliateConversion)->getConnection()),
                includeGlobal: true,
            )) {
            throw new InvalidArgumentException('The attribution program is outside the affiliate owner scope.');
        }
    }

    /**
     * Validate explicit references for attribution-free recording. Link and
     * program integrity is checked against the resolved affiliate: a link
     * must belong to it, a program must exist within its allowed owner
     * scope. Precedence never bypasses these checks.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $provenance
     */
    private function assertStandaloneReferences(ConversionBeneficiary $affiliate, array $payload, array $provenance): void
    {
        $linkId = $this->mergedValue($provenance, $payload, null, 'affiliate_link_id');
        $programId = $this->mergedValue($provenance, $payload, null, 'affiliate_program_id');

        if ($linkId !== null && (! is_string($linkId) || ! app(TrackingLinkReader::class)->belongsTo($linkId, (string) $affiliate->reference->id))) {
            throw new InvalidArgumentException('The outcome link does not belong to its affiliate.');
        }

        if ($programId !== null && (! is_string($programId) || ! app(ProgramReferenceReader::class)->existsForOwner(
            $programId,
            $affiliate->reference->ownerType,
            $affiliate->reference->ownerId,
            spl_object_id((new AffiliateConversion)->getConnection()),
            includeGlobal: true,
        ))) {
            throw new InvalidArgumentException('The outcome program is outside the affiliate owner scope.');
        }
    }

    private function assertSameConnection(): void
    {
        $connection = (new AffiliateConversion)->getConnection();

        if ((new AffiliateBalance)->getConnection() !== $connection) {
            throw new LogicException('Affiliate outcome recording requires one shared connection instance for atomicity.');
        }

        app(FraudSignalMaintenance::class)->assertConnection($connection->getName(), spl_object_id($connection));
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }
}
