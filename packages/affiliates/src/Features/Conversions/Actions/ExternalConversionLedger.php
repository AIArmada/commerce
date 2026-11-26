<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Actions;

use AIArmada\Affiliates\Contracts\MerchantIdentity;
use AIArmada\Affiliates\Contracts\MerchantLedger;
use AIArmada\Affiliates\Data\ExternalConversion;
use AIArmada\Affiliates\Data\PostedConversion;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Features\Commissions\Api\CommissionPricing;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateBalance;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\States\ApprovedConversion;
use AIArmada\Affiliates\Features\Conversions\States\RejectedConversion;
use AIArmada\Affiliates\Features\Fraud\Api\FraudApi;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Merchant books for externally-attributed conversions.
 *
 * Commission math arrives resolved on the draft; this service persists
 * the row, runs fraud and accounting, and emits the conversion event.
 * Posting is idempotent on (source, source ref, external reference).
 */
final readonly class ExternalConversionLedger implements MerchantLedger
{
    /** @var list<string> */
    private const ACTIVE_POSTING_STATUSES = ['pending', 'approved', 'qualified', 'paid'];

    public function __construct(
        private MerchantIdentity $identities,
        private ApplyConversionAccounting $accounting,
        private FraudApi $fraud,
        private Dispatcher $events,
        private VoidAffiliateConversion $void,
    ) {}

    public function postExternalConversion(ExternalConversion $draft): ?PostedConversion
    {
        $affiliate = $this->resolveAffiliate($draft);

        if (! $affiliate instanceof Affiliate) {
            return null;
        }

        $connection = (new AffiliateConversion)->getConnection();

        if ($affiliate->getConnection() !== $connection || (new AffiliateBalance)->getConnection() !== $connection) {
            throw new RuntimeException('External conversion accounting requires a shared database connection.');
        }

        $owner = $affiliate->owner;
        if ($affiliate->owner_id !== null && $owner === null) {
            throw new RuntimeException('External conversion affiliate owner no longer exists.');
        }

        $autoApprove = (bool) config('affiliates.commissions.auto_approve', false);
        $idempotencyKey = hash('sha256', implode('|', [
            $draft->source,
            $draft->sourceRef,
            $draft->externalReference,
        ]));
        $attributes = [
            'idempotency_key' => $idempotencyKey,
            'affiliate_id' => $affiliate->getKey(),
            'affiliate_code' => $affiliate->code,
            'source_ref' => $draft->sourceRef,
            'subject_type' => $draft->metadata['subject_type'] ?? 'external_order',
            'subject_key' => $draft->externalReference,
            'external_reference' => $draft->externalReference,
            'conversion_type' => 'external',
            'subtotal_minor' => $draft->revenueMinor,
            'value_minor' => $draft->revenueMinor,
            'commission_minor' => app(CommissionPricing::class)->clamp($draft->commissionMinor),
            'commission_currency' => $draft->currency,
            'status' => $autoApprove ? ApprovedConversion::class : config('affiliates.commissions.default_status', 'pending'),
            'origin' => $draft->source,
            'metadata' => $draft->metadata,
            'occurred_at' => CarbonImmutable::now(),
            'approved_at' => $autoApprove ? CarbonImmutable::now() : null,
            'owner_type' => $affiliate->owner_type,
            'owner_id' => $affiliate->owner_id,
        ];

        $conversion = OwnerContext::withOwner($owner, fn (): AffiliateConversion => $connection->transaction(function () use ($attributes, $connection): AffiliateConversion {
            $conversion = $this->createIdempotent($attributes);
            $this->assertPostingIdentity($conversion, $attributes);

            if (! $conversion->wasRecentlyCreated) {
                return $conversion;
            }

            if (! $this->fraud->analyzeConversion((string) $conversion->getKey())->allowed) {
                $conversion->update(['status' => RejectedConversion::class]);
            }

            $this->accounting->handle($conversion);

            if (! $conversion->status->equals(RejectedConversion::class)) {
                $conversionData = $conversion->toData();
                $connection->afterCommit(fn () => $this->events->dispatch(new AffiliateConversionRecorded($conversionData)));
            }

            return $conversion;
        }, attempts: 3));

        return self::toPostedConversion($conversion->fresh() ?? $conversion);
    }

    public function findPosted(string $source, string $sourceRef): ?PostedConversion
    {
        $conversion = AffiliateConversion::query()
            ->withoutOwnerScope()
            ->where('origin', $source)
            ->where('source_ref', $sourceRef)
            ->where('conversion_type', 'external')
            ->orderBy('id')
            ->first();

        return $conversion instanceof AffiliateConversion ? self::toPostedConversion($conversion) : null;
    }

    public function findPosting(string $source, string $sourceRef, string $externalReference): ?PostedConversion
    {
        $conversion = $this->queryPosted($source, $sourceRef, $externalReference)->first();

        return $conversion instanceof AffiliateConversion ? self::toPostedConversion($conversion) : null;
    }

    public function voidPosted(string $source, string $sourceRef, string $externalReference, string $reason): ?PostedConversion
    {
        $conversion = $this->queryPosted($source, $sourceRef, $externalReference)->first();

        if (! $conversion instanceof AffiliateConversion) {
            return null;
        }

        // The lookup above is deliberately cross-tenant, but the void
        // action re-reads owner-scoped: restore the row's own owner so
        // the void resolves on ownerless (network) call paths too.
        $owner = $conversion->owner;
        if ($conversion->owner_id !== null && $owner === null) {
            throw new RuntimeException('External conversion owner no longer exists.');
        }

        OwnerContext::withOwner($owner, fn (): AffiliateConversion => $this->void->handle($conversion, $reason));

        $settled = AffiliateConversion::query()->withoutOwnerScope()->whereKey($conversion->getKey())->firstOrFail();

        return self::toPostedConversion($settled);
    }

    public function postingsForSourceRef(string $source, string $sourceRef): array
    {
        return AffiliateConversion::query()
            ->withoutOwnerScope()
            ->where('origin', $source)
            ->where('source_ref', $sourceRef)
            ->whereIn('status', self::ACTIVE_POSTING_STATUSES)
            ->get(['commission_currency', 'value_minor', 'commission_minor', 'external_reference'])
            ->map(fn (AffiliateConversion $row): array => [
                'commission_currency' => $row->commission_currency,
                'value_minor' => (int) $row->value_minor,
                'commission_minor' => (int) $row->commission_minor,
                'external_reference' => $row->external_reference,
            ])
            ->all();
    }

    public function postingsForExternalReference(string $externalReference): array
    {
        return AffiliateConversion::query()
            ->withoutOwnerScope()
            ->where('external_reference', $externalReference)
            ->whereIn('status', self::ACTIVE_POSTING_STATUSES)
            ->get(['origin', 'source_ref', 'commission_currency', 'commission_minor'])
            ->map(fn (AffiliateConversion $row): array => [
                'origin' => $row->origin,
                'source_ref' => $row->source_ref,
                'commission_currency' => $row->commission_currency,
                'commission_minor' => (int) $row->commission_minor,
            ])
            ->all();
    }

    private function resolveAffiliate(ExternalConversion $draft): ?Affiliate
    {
        $affiliate = Affiliate::query()->withoutOwnerScope()->whereKey($draft->affiliateId)->first();

        if ($affiliate instanceof Affiliate) {
            return $affiliate;
        }

        if (! is_string($draft->affiliateEmail) || $draft->affiliateEmail === '') {
            return null;
        }

        $id = $this->identities->findIdForEmail($draft->affiliateEmail);

        if (! is_string($id)) {
            return null;
        }

        $affiliate = Affiliate::query()->withoutOwnerScope()->whereKey($id)->first();

        return $affiliate instanceof Affiliate ? $affiliate : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createIdempotent(array $attributes): AffiliateConversion
    {
        $existing = AffiliateConversion::query()
            ->withoutOwnerScope()
            ->where('idempotency_key', $attributes['idempotency_key'])
            ->first();

        if ($existing instanceof AffiliateConversion) {
            $existing->wasRecentlyCreated = false;

            return $existing;
        }

        try {
            $conversion = (new AffiliateConversion)->getConnection()->transaction(function () use ($attributes): AffiliateConversion {
                $conversion = new AffiliateConversion;
                $conversion->forceFill($attributes)->save();

                return $conversion;
            }, attempts: 1);
        } catch (QueryException $exception) {
            if (! self::isUniqueViolation($exception)) {
                throw $exception;
            }

            $existing = AffiliateConversion::query()
                ->withoutOwnerScope()
                ->where('idempotency_key', $attributes['idempotency_key'])
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            $existing->wasRecentlyCreated = false;

            return $existing;
        }

        $conversion->wasRecentlyCreated = true;

        return $conversion;
    }

    /** @param array<string, mixed> $attributes */
    private function assertPostingIdentity(AffiliateConversion $conversion, array $attributes): void
    {
        foreach (['origin', 'source_ref', 'external_reference', 'affiliate_id', 'conversion_type'] as $field) {
            if ((string) $conversion->getAttribute($field) !== (string) $attributes[$field]) {
                throw new InvalidArgumentException('External conversion idempotency key conflicts with an existing posting.');
            }
        }
    }

    private static function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }

    /**
     * @return Builder<AffiliateConversion>
     */
    private function queryPosted(string $source, string $sourceRef, string $externalReference): Builder
    {
        return AffiliateConversion::query()
            ->withoutOwnerScope()
            ->where('origin', $source)
            ->where('source_ref', $sourceRef)
            ->where('external_reference', $externalReference)
            ->where('conversion_type', 'external');
    }

    private static function toPostedConversion(AffiliateConversion $conversion): PostedConversion
    {
        return new PostedConversion(
            id: (string) $conversion->getKey(),
            affiliateCode: (string) $conversion->affiliate_code,
            commissionMinor: (int) $conversion->commission_minor,
            commissionCurrency: (string) $conversion->commission_currency,
            status: $conversion->status->getMorphClass(),
        );
    }
}
