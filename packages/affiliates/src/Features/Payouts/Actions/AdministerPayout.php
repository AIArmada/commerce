<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Actions;

use AIArmada\Affiliates\Data\PayoutResult;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutAdministration;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;

final readonly class AdministerPayout implements PayoutAdministration
{
    public function __construct(
        private CreatePayout $createPayout,
        private ProcessAffiliatePayout $processPayout,
        private UpdatePayoutStatus $updatePayoutStatus,
    ) {}

    public function create(string $affiliateId, array $conversionIds, array $attributes = []): string
    {
        return (string) $this->createPayout->handle($conversionIds, $attributes, $affiliateId)->getKey();
    }

    public function process(string $payoutId): PayoutResult
    {
        return $this->processPayout->handle($this->findPayout($payoutId));
    }

    public function updateStatus(string $payoutId, string $status, ?string $notes = null, bool $retainNote = false): void
    {
        $this->updatePayoutStatus->handle($this->findPayout($payoutId), $status, $notes, retainNote: $retainNote);
    }

    private function findPayout(string $payoutId): AffiliatePayout
    {
        return (bool) config('affiliates.owner.enabled', false)
            ? OwnerWriteGuard::findOrFailForOwner(AffiliatePayout::class, $payoutId)
            : AffiliatePayout::query()->findOrFail($payoutId);
    }
}
