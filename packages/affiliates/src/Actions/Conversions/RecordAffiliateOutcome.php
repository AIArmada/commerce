<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Conversions;

use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Data\AffiliateOutcomeResult;
use AIArmada\Affiliates\Enums\AffiliateOutcomeDisposition;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Services\Commissions\CommissionCaps;
use AIArmada\Affiliates\Services\FraudDetectionService;
use AIArmada\Affiliates\States\RejectedConversion;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

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
final class RecordAffiliateOutcome
{
    use AsAction;

    public function __construct(
        private readonly Dispatcher $events,
        private readonly ApplyConversionAccounting $accounting,
        private readonly FraudDetectionService $fraud,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(
        AffiliateAttribution $attribution,
        string $conversionType,
        string $businessKey,
        array $payload = [],
    ): AffiliateOutcomeResult {
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

        $this->assertAttributionConnection($attribution);

        if ((bool) config('affiliates.owner.enabled', false)) {
            /** @var AffiliateAttribution $attribution */
            $attribution = OwnerWriteGuard::findOrFailForOwner(AffiliateAttribution::class, $attribution->getKey());
        } else {
            $attribution = AffiliateAttribution::query()->findOrFail($attribution->getKey());
        }

        $affiliate = $attribution->affiliate()->first();

        if ($affiliate === null) {
            return new AffiliateOutcomeResult(AffiliateOutcomeDisposition::NoAffiliate, null);
        }

        if ((bool) config('affiliates.owner.enabled', false)) {
            /** @var Affiliate $affiliate */
            $affiliate = OwnerWriteGuard::findOrFailForOwner(Affiliate::class, $affiliate->getKey());
        }

        $this->assertSameConnection($attribution, $affiliate);
        $this->assertSameOwner($attribution, $affiliate);
        $this->assertAttributionReferences($attribution, $affiliate, $payload);

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

        $connection = (new AffiliateConversion)->getConnection();

        /** @var array{disposition: AffiliateOutcomeDisposition, conversion_id: string} $committed */
        $committed = $connection->transaction(function () use (
            $connection,
            $affiliate,
            $attribution,
            $conversionType,
            $idempotencyKey,
            $externalReference,
            $metadata,
            $payload
        ): array {
            // Fresh instance per attempt: reusing a saved model across
            // outer-transaction retries leaves exists=true after rollback,
            // turning the replayed save into a silent no-op (phantom).
            $conversion = new AffiliateConversion([
                'idempotency_key' => $idempotencyKey,
                'affiliate_id' => $affiliate->getKey(),
                'affiliate_attribution_id' => $attribution->getKey(),
                'affiliate_program_id' => $attribution->affiliate_program_id,
                'affiliate_code' => $affiliate->code,
                'subject_type' => Arr::get($payload, 'subject_type', $attribution->subject_type),
                'subject_key' => Arr::get($payload, 'subject_key', $attribution->subject_key),
                'subject_instance' => Arr::get($payload, 'subject_instance', $attribution->subject_instance),
                'subject_title_snapshot' => Arr::get($payload, 'subject_title_snapshot', $attribution->subject_title_snapshot),
                'subject_id' => Arr::get($payload, 'subject_id', $attribution->subject_id),
                'affiliate_link_id' => $attribution->affiliate_link_id,
                'external_reference' => $externalReference,
                'commission_override' => Arr::get($payload, 'commission_override', $attribution->commission_override),
                'upline_levels' => Arr::get($payload, 'upline_levels', $attribution->upline_levels),
                'conversion_type' => $conversionType,
                'subtotal_minor' => (int) Arr::get($payload, 'subtotal_minor', 0),
                'value_minor' => (int) Arr::get($payload, 'value_minor', 0),
                'commission_minor' => CommissionCaps::clamp((int) Arr::get($payload, 'commission_minor', 0)),
                'commission_currency' => (string) Arr::get($payload, 'commission_currency', config('affiliates.currency.default', 'MYR')),
                'status' => Arr::get($payload, 'status', config('affiliates.commissions.default_status', 'pending')),
                'channel' => Arr::get($payload, 'channel'),
                'origin' => Arr::get($payload, 'origin', $attribution->origin),
                'sharer_user_id' => Arr::get($payload, 'sharer_user_id', $attribution->sharer_user_id),
                'actor_user_id' => Arr::get($payload, 'actor_user_id'),
                'metadata' => $metadata,
                'occurred_at' => Arr::get($payload, 'occurred_at', CarbonImmutable::now()),
                'approved_at' => Arr::get($payload, 'approved_at'),
            ]);
            $conversion->forceFill([
                'owner_type' => $affiliate->owner_type,
                'owner_id' => $affiliate->owner_id,
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

            $fraudVerdict = $this->fraud->analyzeConversion($conversion);
            $rejected = ! ($fraudVerdict['allowed'] ?? true);

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
            AffiliateConversionData::fromModel($stored),
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

    private function assertAttributionConnection(AffiliateAttribution $attribution): void
    {
        $expected = (new AffiliateConversion)->getConnection();

        if ($attribution->getConnection()->getName() !== $expected->getName()
            || spl_object_id($attribution->getConnection()) !== spl_object_id($expected)) {
            throw new LogicException('Affiliate outcome recording requires the attribution on the same resolved connection as conversions.');
        }
    }

    private function assertSameOwner(AffiliateAttribution $attribution, Affiliate $affiliate): void
    {
        if ($attribution->owner_type !== $affiliate->owner_type
            || (string) $attribution->owner_id !== (string) $affiliate->owner_id) {
            throw new LogicException('Affiliate outcome recording requires an attribution and affiliate with the same owner.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertAttributionReferences(AffiliateAttribution $attribution, Affiliate $affiliate, array $payload): void
    {
        foreach (['affiliate_link_id', 'affiliate_program_id'] as $key) {
            if (array_key_exists($key, $payload)
                && ((! is_string($payload[$key]) && ! is_int($payload[$key]) && $payload[$key] !== null)
                    || (string) $payload[$key] !== (string) $attribution->getAttribute($key))) {
                throw new InvalidArgumentException(sprintf('Outcome payload [%s] must match the persisted attribution.', $key));
            }
        }

        if ($attribution->affiliate_link_id !== null
            && ! $affiliate->links()->whereKey($attribution->affiliate_link_id)->exists()) {
            throw new InvalidArgumentException('The attribution link does not belong to its affiliate.');
        }

        if ($attribution->affiliate_program_id !== null
            && ! AffiliateProgram::query()
                ->forOwner($affiliate->owner, includeGlobal: true)
                ->whereKey($attribution->affiliate_program_id)
                ->exists()) {
            throw new InvalidArgumentException('The attribution program is outside the affiliate owner scope.');
        }
    }

    private function assertSameConnection(AffiliateAttribution $attribution, mixed $affiliate): void
    {
        $conversion = new AffiliateConversion;
        $fraudSignal = new AffiliateFraudSignal;
        $balance = new AffiliateBalance;

        $resolved = [
            'attribution' => $attribution->getConnection()->getName(),
            'affiliate' => $affiliate->getConnection()->getName(),
            'conversion' => $conversion->getConnection()->getName(),
            'fraud_signal' => $fraudSignal->getConnection()->getName(),
            'balance' => $balance->getConnection()->getName(),
        ];

        $unique = array_unique(array_values($resolved));

        if (count($unique) !== 1) {
            throw new LogicException('Affiliate outcome recording requires the attribution, affiliate, conversion, fraud-signal, and balance writes on the same resolved connection.');
        }

        $instances = [
            spl_object_id($attribution->getConnection()),
            spl_object_id($affiliate->getConnection()),
            spl_object_id($conversion->getConnection()),
            spl_object_id($fraudSignal->getConnection()),
            spl_object_id($balance->getConnection()),
        ];

        if (count(array_unique($instances)) !== 1) {
            throw new LogicException('Affiliate outcome recording requires one shared connection instance for atomicity.');
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }
}
