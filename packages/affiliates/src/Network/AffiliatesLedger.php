<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Network;

use AIArmada\AffiliateNetwork\Contracts\NetworkLedger;
use AIArmada\AffiliateNetwork\Data\NetworkConversionDraft;
use AIArmada\AffiliateNetwork\Data\NetworkPostedConversion;
use AIArmada\AffiliateNetwork\Support\UserKeyAffiliateIdentityResolver;
use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Services\Commissions\CommissionCaps;
use AIArmada\Affiliates\Services\FraudDetectionService;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\RejectedConversion;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;

/**
 * Post network conversions to the affiliates ledger.
 *
 * The network computes commission and currency on the draft; this adapter
 * persists the ledger row, runs fraud and accounting, and emits the
 * core conversion event. Posting is idempotent on (network link,
 * external reference).
 */
final class AffiliatesLedger implements NetworkLedger
{
    public function __construct(
        private readonly ApplyConversionAccounting $accounting,
        private readonly FraudDetectionService $fraud,
        private readonly Dispatcher $events,
    ) {}

    public function post(NetworkConversionDraft $draft): ?NetworkPostedConversion
    {
        // The ledger is keyed by opaque network ids plus the idempotency
        // key; ambient owner scope (e.g. the link owner's context from
        // recordConversion) must not hide the affiliate or prior postings.
        $affiliate = Affiliate::query()->withoutOwnerScope()->whereKey($draft->affiliateId)->first();

        if (! $affiliate instanceof Affiliate) {
            $affiliate = $this->affiliateForNetworkUser($draft->affiliateId);
        }

        if (! $affiliate instanceof Affiliate) {
            return null;
        }

        $existing = $this->queryPosted($draft->linkId, $draft->externalReference)->first();

        if ($existing instanceof AffiliateConversion) {
            return self::toPostedConversion($existing);
        }

        $autoApprove = (bool) config('affiliates.commissions.auto_approve', false);

        $conversion = new AffiliateConversion([
            'idempotency_key' => hash('sha256', implode('|', [
                'network',
                $draft->linkId,
                $draft->externalReference,
            ])),
            'affiliate_id' => $affiliate->getKey(),
            'affiliate_code' => $affiliate->code,
            'network_link_id' => $draft->linkId,
            'subject_type' => 'network_order',
            'subject_key' => $draft->externalReference,
            'external_reference' => $draft->externalReference,
            'conversion_type' => 'network',
            'subtotal_minor' => $draft->revenueMinor,
            'value_minor' => $draft->revenueMinor,
            'commission_minor' => CommissionCaps::clamp($draft->commissionMinor),
            'commission_currency' => $draft->currency,
            'status' => $autoApprove ? ApprovedConversion::class : config('affiliates.commissions.default_status', 'pending'),
            'origin' => 'network',
            'metadata' => [
                'offer_id' => $draft->offerId,
                'link_code' => $draft->linkCode,
                'site_id' => $draft->siteId,
            ],
            'occurred_at' => CarbonImmutable::now(),
            'approved_at' => $autoApprove ? CarbonImmutable::now() : null,
        ]);
        $conversion->forceFill([
            'owner_type' => $affiliate->owner_type,
            'owner_id' => $affiliate->owner_id,
        ]);
        $conversion->save();

        if (! $this->fraud->analyzeConversion($conversion)['allowed']) {
            $conversion->update(['status' => RejectedConversion::class]);
        }

        $this->accounting->handle($conversion);
        $this->events->dispatch(new AffiliateConversionRecorded(AffiliateConversionData::fromModel($conversion)));

        return self::toPostedConversion($conversion->fresh() ?? $conversion);
    }

    public function findPosted(string $linkId, string $externalReference): ?NetworkPostedConversion
    {
        $conversion = $this->queryPosted($linkId, $externalReference)->first();

        return $conversion instanceof AffiliateConversion ? self::toPostedConversion($conversion) : null;
    }

    public function rowsForLink(string $linkId): array
    {
        return AffiliateConversion::query()
            ->withoutOwnerScope()
            ->where('network_link_id', $linkId)
            ->get(['commission_currency', 'value_minor', 'commission_minor'])
            ->map(fn (AffiliateConversion $row): array => [
                'commission_currency' => $row->commission_currency,
                'value_minor' => (int) $row->value_minor,
                'commission_minor' => (int) $row->commission_minor,
            ])
            ->all();
    }

    /**
     * Map a network user id to its merchant affiliate row via the account
     * email.
     *
     * Joining never creates this linkage — it exists only when the
     * merchant side already knows the email (portal signup with the same
     * address, admin entry). The id arrives from an authenticated
     * link-creation chain, and only Active affiliates match. Unknown
     * users post nothing; the network keeps counters-only.
     */
    private function affiliateForNetworkUser(string $affiliateId): ?Affiliate
    {
        $email = (new UserKeyAffiliateIdentityResolver)->find($affiliateId)?->email;

        if (! is_string($email) || $email === '') {
            return null;
        }

        $id = (new AffiliatesIdentityResolver)->findIdForVerifiedEmail($email);

        if (! is_string($id)) {
            return null;
        }

        $affiliate = Affiliate::query()->withoutOwnerScope()->whereKey($id)->first();

        return $affiliate instanceof Affiliate ? $affiliate : null;
    }

    /**
     * @return Builder<AffiliateConversion>
     */
    private function queryPosted(string $linkId, string $externalReference): Builder
    {
        return AffiliateConversion::query()
            ->withoutOwnerScope()
            ->where('network_link_id', $linkId)
            ->where('external_reference', $externalReference);
    }

    private static function toPostedConversion(AffiliateConversion $conversion): NetworkPostedConversion
    {
        return new NetworkPostedConversion(
            id: (string) $conversion->getKey(),
            affiliateCode: (string) $conversion->affiliate_code,
            commissionMinor: (int) $conversion->commission_minor,
            commissionCurrency: (string) $conversion->commission_currency,
            status: $conversion->status->getMorphClass(),
        );
    }
}
