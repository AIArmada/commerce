<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Conversions;

use AIArmada\Affiliates\Contracts\AffiliateAwardPolicy;
use AIArmada\Affiliates\Data\AffiliateAwardDecision;
use AIArmada\Affiliates\Data\AffiliateAwardProposal;
use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Data\AffiliateOutcomeResult;
use AIArmada\Affiliates\Enums\AffiliateOutcomeDisposition;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Exceptions\AffiliateAwardPolicyLimitException;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\ConversionStatus;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\Support\Awards\AffiliateAwardKey;
use AIArmada\Affiliates\Support\Awards\NullAffiliateAwardPolicy;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Post a generic fixed-amount monetary award as its own backing conversion.
 *
 * Fixed awards are entitlements, not earned commissions: the action posts
 * exactly the validated positive minor-unit amount or rejects before any
 * money effect. It never applies commercial `CommissionCaps`, never derives
 * an amount from a percentage of zero, and never mutates an existing
 * zero-value row — every award is a separate new `reward_award` conversion
 * with a null attribution.
 *
 * Identity: the caller supplies a bounded caller-owned namespaced award key
 * (for example `acme:award:2026-10-07:9f2c`). The package hashes the exact
 * validated identity (`sha256(awardKey)`) for the unique `idempotency_key`,
 * independent of recipient/owner/link. Lookup, insertion, and collision
 * recovery all use that same hash. An identical retry returns the stored
 * row untouched — frozen recipient/owner/amount/currency/backing, zero new
 * accounting, zero fraud writes, zero events. Reusing a recorded key with a
 * different recipient, owner, amount, currency, or backing type fails
 * explicitly before any new effect and discloses no stored foreign data.
 *
 * Authorization: the host proves caller authorization and recipient
 * eligibility behind `AffiliateAwardPolicy`, invoked INSIDE the posting
 * transaction after the locked idempotency preflight and the locked
 * recipient read. A missing binding resolves to the null policy and
 * returns a truthful `Rejected` refusal with zero money effects, as does
 * an explicit denial. Only an affirmative decision posts new money.
 * Optional caller policy limits from that decision are enforced
 * exact-or-reject inside the transaction before any write. This action
 * has no host-namespace dependency, keeps no interpretation of host
 * membership or funding, runs no fraud rules of its own, and dispatches
 * no downline, bonus, payout, or transport side effects — only the shared
 * conversion-recorded domain event on creation (after commit, gated by
 * `dispatch_event`).
 *
 * Attribution status (`canBeAttributed`) is necessary but never
 * sufficient for new money: every new posting additionally requires an
 * affirmative host decision. Identical replays of an already-recorded
 * award settle as `Replayed` without fresh authorization — frozen row,
 * zero new accounting, zero events — so earned obligations survive later
 * deactivation or denial; binding mismatches still throw without
 * disclosing stored foreign data.
 *
 * Transaction: one same-connection transaction covers the locked
 * idempotency preflight, the authoritative locked recipient read, the
 * host authorization call, insertion via a savepoint for
 * PostgreSQL-safe unique recovery, and creation accounting through the
 * shared `ApplyConversionAccounting` matrix. Because the decision runs
 * inside the transaction, same-connection `SELECT ... FOR UPDATE` locks
 * taken by the host policy are held through commit, and a concurrent
 * deactivation or correction that commits first is observed instead of
 * slipped through. Recipient, conversion, and balance must resolve to
 * the same connection name and instance or the action fails closed.
 * After-commit dispatch is registered only after the transaction
 * commits, so deadlock retries never double-announce.
 */
final class RecordAffiliateAward
{
    use AsAction;

    public const CONVERSION_TYPE = 'reward_award';

    /**
     * @var list<string>
     */
    private const ALLOWED_ATTRIBUTES = [
        'status',
        'external_reference',
        'source_ref',
        'channel',
        'origin',
        'metadata',
        'dispatch_event',
        'occurred_at',
    ];

    private const MAX_METADATA_ENTRIES = 32;

    private const MAX_METADATA_KEY_LENGTH = 64;

    private const MAX_METADATA_STRING_LENGTH = 500;

    public function __construct(
        private readonly Dispatcher $events,
        private readonly ApplyConversionAccounting $accounting,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        Affiliate $affiliate,
        string $awardKey,
        int $amountMinor,
        string $currency,
        array $attributes = [],
    ): AffiliateOutcomeResult {
        $key = AffiliateAwardKey::parse($awardKey);

        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Award amount must be a positive integer minor-unit amount.');
        }

        $currency = mb_strtoupper(mb_trim($currency));

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Award currency must be an explicit 3-letter ISO-4217 code.');
        }

        $this->assertAllowedAttributes($attributes);

        $statusClass = $this->resolveCreationStatus(Arr::get($attributes, 'status', ApprovedConversion::class));
        $externalReference = $this->sanitizeReference(Arr::get($attributes, 'external_reference'), $key->value);
        $sourceRef = $this->sanitizeOptionalReference(Arr::get($attributes, 'source_ref'), 'Source reference');
        $channel = $this->sanitizeBoundedString(Arr::get($attributes, 'channel'), 64, 'Channel');
        $origin = $this->sanitizeBoundedString(Arr::get($attributes, 'origin'), 32, 'Origin');
        $metadata = $this->validateMetadata(Arr::get($attributes, 'metadata', []));
        $occurredAt = $this->resolveOccurredAt(Arr::get($attributes, 'occurred_at'));
        $dispatchEvent = (bool) Arr::get($attributes, 'dispatch_event', true);

        $this->assertSameConnection($affiliate);

        $proposal = new AffiliateAwardProposal(
            awardKey: $key->value,
            amountMinor: $amountMinor,
            currency: $currency,
            statusClass: $statusClass,
            externalReference: $externalReference,
            sourceRef: $sourceRef,
        );

        $policy = $this->resolvePolicy();

        $idempotencyKey = $key->idempotencyKey();
        $requestedAffiliateId = (string) $affiliate->getKey();

        $connection = (new AffiliateConversion)->getConnection();

        /** @var array{disposition: AffiliateOutcomeDisposition, conversion_id: ?string} $committed */
        $committed = $connection->transaction(function () use (
            $connection,
            $policy,
            $proposal,
            $idempotencyKey,
            $requestedAffiliateId,
            $amountMinor,
            $currency,
            $statusClass,
            $externalReference,
            $sourceRef,
            $channel,
            $origin,
            $metadata,
            $occurredAt,
        ): array {
            // Lock order matches the shared accounting matrix
            // (conversion, then affiliate, then balance) so concurrent
            // awards serialize instead of deadlocking across engines.
            $existing = AffiliateConversion::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            // Authoritative recipient read: locked, same transaction and
            // connection as posting, so a concurrent deactivation or
            // correction that commits first is observed here and can
            // never slip through a stale pre-transaction preflight.
            $recipient = Affiliate::query()
                ->whereKey($requestedAffiliateId)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof AffiliateConversion) {
                // Settlement path: the earned obligation is already
                // recorded, so an identical retry settles without fresh
                // authorization and without any new effect. Owner
                // binding still requires the locked recipient row; a
                // missing recipient fails closed with no disclosure.
                if (! $recipient instanceof Affiliate) {
                    return [
                        'disposition' => AffiliateOutcomeDisposition::NoAffiliate,
                        'conversion_id' => null,
                    ];
                }

                $this->assertReplayMatches($existing, $this->buildConversion(
                    $recipient,
                    $idempotencyKey,
                    $amountMinor,
                    $currency,
                    $statusClass,
                    $externalReference,
                    $sourceRef,
                    $channel,
                    $origin,
                    $metadata,
                    $occurredAt,
                ));

                return [
                    'disposition' => AffiliateOutcomeDisposition::Replayed,
                    'conversion_id' => (string) $existing->getKey(),
                ];
            }

            // New money requires an attributable recipient AND an
            // affirmative host decision, both evaluated here inside the
            // lock boundary. Attribution status alone never posts.
            if (! $recipient instanceof Affiliate || ! $recipient->canBeAttributed()) {
                return [
                    'disposition' => AffiliateOutcomeDisposition::NoAffiliate,
                    'conversion_id' => null,
                ];
            }

            // The host decides inside the posting transaction on the
            // award connection: same-connection approval/source locks
            // taken here are held through commit. The call must stay
            // read-only because deadlock retries re-invoke it.
            $decision = $policy->decide($recipient, $proposal);

            if (! $decision->authorized) {
                return [
                    'disposition' => AffiliateOutcomeDisposition::Rejected,
                    'conversion_id' => null,
                ];
            }

            $this->assertWithinPolicyLimits($amountMinor, $currency, $decision);

            $conversion = $this->buildConversion(
                $recipient,
                $idempotencyKey,
                $amountMinor,
                $currency,
                $statusClass,
                $externalReference,
                $sourceRef,
                $channel,
                $origin,
                $metadata,
                $occurredAt,
            );

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

                $this->assertReplayMatches($raced, $conversion);

                return [
                    'disposition' => AffiliateOutcomeDisposition::Replayed,
                    'conversion_id' => (string) $raced->getKey(),
                ];
            }

            $this->accounting->handle($conversion);

            return [
                'disposition' => AffiliateOutcomeDisposition::Created,
                'conversion_id' => (string) $conversion->getKey(),
            ];
            // Concurrent same-key inserts deadlock on gap locks under
            // InnoDB; the retry replays the committed winner row.
        }, 3);

        if ($committed['conversion_id'] === null) {
            return new AffiliateOutcomeResult($committed['disposition'], null);
        }

        $stored = AffiliateConversion::query()->whereKey($committed['conversion_id'])->firstOrFail();
        $result = new AffiliateOutcomeResult(
            $committed['disposition'],
            AffiliateConversionData::fromModel($stored),
        );

        // Only creation announces: replays stay silent so a retried award
        // can never double-advance projections or duplicate notifications.
        if ($dispatchEvent && $result->isCreated()) {
            $conversionData = $result->conversion;
            $connection->afterCommit(function () use ($conversionData): void {
                if ($conversionData instanceof AffiliateConversionData) {
                    $this->events->dispatch(new AffiliateConversionRecorded($conversionData));
                }
            });
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertAllowedAttributes(array $attributes): void
    {
        $unknown = array_values(array_diff(array_keys($attributes), self::ALLOWED_ATTRIBUTES));

        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'Unknown award attribute [%s]; allowed attributes are [%s]. Attribution, subject, upline, and commission inputs are never accepted on awards.',
                implode(', ', $unknown),
                implode(', ', self::ALLOWED_ATTRIBUTES),
            ));
        }
    }

    /**
     * @return class-string
     */
    private function resolveCreationStatus(mixed $status): string
    {
        if (! is_string($status) && ! $status instanceof ConversionStatus) {
            throw new InvalidArgumentException('Award status must be an explicit pending or approved conversion status.');
        }

        $statusClass = ConversionStatus::resolveStateClassFor($status);

        if ($statusClass !== PendingConversion::class && $statusClass !== ApprovedConversion::class) {
            throw new InvalidArgumentException(sprintf(
                'Award status [%s] cannot be created; awards are created pending or approved only.',
                $statusClass,
            ));
        }

        return $statusClass;
    }

    private function sanitizeReference(mixed $value, string $awardKey): string
    {
        if ($value !== null && ! is_string($value)) {
            throw new InvalidArgumentException('Award external reference must be a string of at most 120 characters.');
        }

        $reference = $value === null ? '' : mb_trim($value);

        if ($reference === '') {
            return mb_substr($awardKey, 0, 120);
        }

        if (mb_strlen($reference) > 120) {
            throw new InvalidArgumentException('Award external reference must be a string of at most 120 characters.');
        }

        return $reference;
    }

    private function sanitizeOptionalReference(mixed $value, string $label): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf('Award %s must be a string of at most 120 characters.', mb_strtolower($label)));
        }

        $value = mb_trim($value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > 120) {
            throw new InvalidArgumentException(sprintf('Award %s must be a string of at most 120 characters.', mb_strtolower($label)));
        }

        return $value;
    }

    private function sanitizeBoundedString(mixed $value, int $maxLength, string $label): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf('Award %s must be a string of at most %d characters.', mb_strtolower($label), $maxLength));
        }

        $value = mb_trim($value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException(sprintf('Award %s must be a string of at most %d characters.', mb_strtolower($label), $maxLength));
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateMetadata(mixed $metadata): array
    {
        if (! is_array($metadata)) {
            throw new InvalidArgumentException('Award metadata must be an array of at most 32 scalar entries.');
        }

        if (count($metadata) > self::MAX_METADATA_ENTRIES) {
            throw new InvalidArgumentException('Award metadata must be an array of at most 32 scalar entries.');
        }

        foreach ($metadata as $metaKey => $metaValue) {
            if (! is_string($metaKey) || mb_trim($metaKey) === '' || mb_strlen($metaKey) > self::MAX_METADATA_KEY_LENGTH) {
                throw new InvalidArgumentException('Award metadata keys must be non-empty strings of at most 64 characters.');
            }

            if (is_string($metaValue) && mb_strlen($metaValue) > self::MAX_METADATA_STRING_LENGTH) {
                throw new InvalidArgumentException('Award metadata string values must not exceed 500 characters.');
            }

            if (! is_scalar($metaValue) && $metaValue !== null) {
                throw new InvalidArgumentException('Award metadata values must be scalar or null.');
            }
        }

        /** @var array<non-empty-string, bool|float|int|string|null> $metadata */
        return $metadata;
    }

    private function resolveOccurredAt(mixed $occurredAt): CarbonImmutable
    {
        if ($occurredAt === null) {
            return CarbonImmutable::now('UTC');
        }

        if ($occurredAt instanceof CarbonInterface) {
            // Normalize to UTC on write so session-timezone display on any
            // engine round-trips the exact same instant.
            return CarbonImmutable::parse($occurredAt->toIso8601String())->utc();
        }

        throw new InvalidArgumentException('Award occurred_at must be null or a date/time instance.');
    }

    private function resolvePolicy(): AffiliateAwardPolicy
    {
        if (app()->bound(AffiliateAwardPolicy::class)) {
            return app(AffiliateAwardPolicy::class);
        }

        return new NullAffiliateAwardPolicy;
    }

    private function assertWithinPolicyLimits(int $amountMinor, string $currency, AffiliateAwardDecision $decision): void
    {
        $minimum = $decision->policyMinMinor;
        $maximum = $decision->policyMaxMinor;

        if ($minimum !== null && $amountMinor < $minimum) {
            throw AffiliateAwardPolicyLimitException::forAmount($amountMinor, $currency, $minimum, $maximum);
        }

        if ($maximum !== null && $amountMinor > $maximum) {
            throw AffiliateAwardPolicyLimitException::forAmount($amountMinor, $currency, $minimum, $maximum);
        }
    }

    private function assertSameConnection(Affiliate $affiliate): void
    {
        $conversion = new AffiliateConversion;
        $balance = new AffiliateBalance;

        $resolved = [
            'affiliate' => $affiliate->getConnection()->getName(),
            'conversion' => $conversion->getConnection()->getName(),
            'balance' => $balance->getConnection()->getName(),
        ];

        if (count(array_unique(array_values($resolved))) !== 1) {
            throw new LogicException('Affiliate award posting requires the recipient, conversion, and balance writes on the same resolved connection.');
        }

        $instances = [
            spl_object_id($affiliate->getConnection()),
            spl_object_id($conversion->getConnection()),
            spl_object_id($balance->getConnection()),
        ];

        if (count(array_unique($instances)) !== 1) {
            throw new LogicException('Affiliate award posting requires one shared connection instance for atomicity.');
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function buildConversion(
        Affiliate $recipient,
        string $idempotencyKey,
        int $amountMinor,
        string $currency,
        string $statusClass,
        string $externalReference,
        ?string $sourceRef,
        ?string $channel,
        ?string $origin,
        array $metadata,
        CarbonImmutable $occurredAt,
    ): AffiliateConversion {
        $conversion = new AffiliateConversion([
            'idempotency_key' => $idempotencyKey,
            'affiliate_id' => $recipient->getKey(),
            'affiliate_attribution_id' => null,
            'affiliate_code' => $recipient->code,
            'external_reference' => $externalReference,
            'source_ref' => $sourceRef,
            'conversion_type' => self::CONVERSION_TYPE,
            'subtotal_minor' => 0,
            'value_minor' => 0,
            'commission_minor' => $amountMinor,
            'commission_currency' => $currency,
            'status' => $statusClass,
            'channel' => $channel,
            'origin' => $origin,
            'metadata' => $metadata,
            'occurred_at' => $occurredAt,
            'approved_at' => $statusClass === ApprovedConversion::class ? CarbonImmutable::now('UTC') : null,
        ]);
        $conversion->forceFill([
            'owner_type' => $recipient->owner_type,
            'owner_id' => $recipient->owner_id,
        ]);

        return $conversion;
    }

    private function assertReplayMatches(AffiliateConversion $stored, AffiliateConversion $requested): void
    {
        $matches = (string) $stored->affiliate_id === (string) $requested->affiliate_id
            && (string) ($stored->owner_type ?? '') === (string) ($requested->owner_type ?? '')
            && (string) ($stored->owner_id ?? '') === (string) ($requested->owner_id ?? '')
            && (int) $stored->commission_minor === (int) $requested->commission_minor
            && mb_strtoupper((string) $stored->commission_currency) === mb_strtoupper((string) $requested->commission_currency)
            && (string) $stored->conversion_type === self::CONVERSION_TYPE;

        if (! $matches) {
            throw new InvalidArgumentException('Award key is already recorded with a different recipient, owner, amount, currency, or backing type.');
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }
}
