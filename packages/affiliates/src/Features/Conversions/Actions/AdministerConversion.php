<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Actions;

use AIArmada\Affiliates\Enums\ConversionStatus as Status;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionAdministration;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\States\ConversionStatus;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Carbon\CarbonImmutable;

final readonly class AdministerConversion implements ConversionAdministration
{
    public function __construct(
        private ApplyConversionAccounting $accounting,
        private ReverseAffiliateConversion $reverser,
        private VoidAffiliateConversion $voider,
    ) {}

    public function transition(string $conversionId, string $status): bool
    {
        $status = Status::from($status);

        return (new AffiliateConversion)->getConnection()->transaction(function () use ($conversionId, $status): bool {
            $conversion = $this->lockConversion($conversionId);
            $target = ConversionStatus::resolveStateClassFor($status->value, $conversion);

            if ($conversion->status->equals($target)) {
                return true;
            }

            if ($status === Status::Reversed) {
                $this->reverser->handle($conversion, 'Administrative reversal');

                return true;
            }

            if (in_array($status, [Status::Rejected, Status::Paid], true)) {
                $conversion->assertNotReservedByOpenPayout();
            }

            $previous = $conversion->status;

            if ($status === Status::Approved) {
                $conversion->approved_at = $conversion->approved_at ?? CarbonImmutable::now();
                $conversion->rejected_at = null;
                $conversion->reversed_at = null;
                $conversion->paid_at = null;
            } elseif ($status === Status::Paid) {
                $conversion->approved_at = $conversion->approved_at ?? CarbonImmutable::now();
                $conversion->paid_at = $conversion->paid_at ?? CarbonImmutable::now();
                $conversion->rejected_at = null;
                $conversion->reversed_at = null;
            } elseif ($status === Status::Rejected) {
                $conversion->rejected_at = $conversion->rejected_at ?? CarbonImmutable::now();
                $conversion->reversed_at = null;
                $conversion->paid_at = null;
            } else {
                $conversion->approved_at = null;
                $conversion->rejected_at = null;
                $conversion->reversed_at = null;
                $conversion->paid_at = null;
            }

            $conversion->status->transitionTo($target);
            $this->accounting->handle($conversion, $previous);

            return true;
        }, attempts: 3);
    }

    public function reverse(string $conversionId, string $reason): void
    {
        (new AffiliateConversion)->getConnection()->transaction(function () use ($conversionId, $reason): void {
            $this->reverser->handle($this->lockConversion($conversionId), $reason);
        }, attempts: 3);
    }

    public function voidConversion(string $conversionId, string $reason): void
    {
        (new AffiliateConversion)->getConnection()->transaction(function () use ($conversionId, $reason): void {
            $this->voider->handle($this->lockConversion($conversionId), $reason);
        }, attempts: 3);
    }

    private function lockConversion(string $conversionId): AffiliateConversion
    {
        if ((bool) config('affiliates.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(AffiliateConversion::class, $conversionId);
        }

        return AffiliateConversion::query()->lockForUpdate()->findOrFail($conversionId);
    }
}
