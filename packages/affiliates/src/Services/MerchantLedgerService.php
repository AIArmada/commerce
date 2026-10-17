<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Actions\Conversions\VoidAffiliateConversion;
use AIArmada\Affiliates\Contracts\MerchantIdentity;
use AIArmada\Affiliates\Contracts\MerchantLedger;
use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Data\ExternalConversion;
use AIArmada\Affiliates\Data\PostedConversion;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Services\Commissions\CommissionCaps;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\RejectedConversion;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Merchant books for externally-attributed conversions.
 *
 * Commission math arrives resolved on the draft; this service persists
 * the row, runs fraud and accounting, and emits the conversion event.
 * Posting is idempotent on (source, source ref, external reference).
 */
final class MerchantLedgerService implements MerchantLedger
{
    /** @var list<string> */
    private const ACTIVE_POSTING_STATUSES = ['pending', 'approved', 'qualified', 'paid'];

    public function __construct(
        private readonly MerchantIdentity $identities,
        private readonly ApplyConversionAccounting $accounting,
        private readonly FraudDetectionService $fraud,
        private readonly Dispatcher $events,
        private readonly VoidAffiliateConversion $void,
    ) {}

    public function postExternalConversion(ExternalConversion $draft): ?PostedConversion
    {
        $affiliate = $this->resolveAffiliate($draft);

        if (! $affiliate instanceof Affiliate) {
            return null;
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
            'commission_minor' => CommissionCaps::clamp($draft->commissionMinor),
            'commission_currency' => $draft->currency,
            'status' => $autoApprove ? ApprovedConversion::class : config('affiliates.commissions.default_status', 'pending'),
            'origin' => $draft->source,
            'metadata' => $draft->metadata,
            'occurred_at' => CarbonImmutable::now(),
            'approved_at' => $autoApprove ? CarbonImmutable::now() : null,
            'owner_type' => $affiliate->owner_type,
            'owner_id' => $affiliate->owner_id,
        ];

        $conversion = DB::transaction(function () use ($attributes): AffiliateConversion {
            $conversion = $this->createIdempotent($attributes);

            if (! $conversion->wasRecentlyCreated) {
                return $conversion;
            }

            if (! $this->fraud->analyzeConversion($conversion)['allowed']) {
                $conversion->update(['status' => RejectedConversion::class]);
            }

            $this->accounting->handle($conversion);

            if (! $conversion->status->equals(RejectedConversion::class)) {
                $conversionData = AffiliateConversionData::fromModel($conversion);
                DB::connection()->afterCommit(fn () => $this->events->dispatch(new AffiliateConversionRecorded($conversionData)));
            }

            return $conversion;
        });

        return self::toPostedConversion($conversion->fresh() ?? $conversion);
    }

    public function findPosted(string $source, string $sourceRef): ?PostedConversion
    {
        $conversion = AffiliateConversion::query()
            ->withoutOwnerScope()
            ->where('origin', $source)
            ->where('source_ref', $sourceRef)
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
        OwnerContext::withOwner($conversion->owner ?? null, fn (): AffiliateConversion => $this->void->handle($conversion, $reason));

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
            $conversion = DB::transaction(function () use ($attributes): AffiliateConversion {
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
                ->firstOrFail();
            $existing->wasRecentlyCreated = false;

            return $existing;
        }

        $conversion->wasRecentlyCreated = true;

        return $conversion;
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
            ->where('external_reference', $externalReference);
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
