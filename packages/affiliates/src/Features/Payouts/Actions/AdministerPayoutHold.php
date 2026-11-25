<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Actions;

use AIArmada\Affiliates\Features\Payouts\Api\PayoutHoldAdministration;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayoutHold;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use LogicException;

final readonly class AdministerPayoutHold implements PayoutHoldAdministration
{
    public function place(string $affiliateId, string $reason, ?string $notes = null, ?DateTimeImmutable $expiresAt = null, ?string $placedBy = null): string
    {
        $validated = validator([
            'reason' => $reason,
            'notes' => $notes,
            'placed_by' => $placedBy,
        ], [
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'placed_by' => ['nullable', 'string', 'max:255'],
        ])->validate();

        return $this->connection()->transaction(function () use ($affiliateId, $validated, $expiresAt): string {
            $affiliate = $this->lockAffiliate($affiliateId);
            $hold = $affiliate->payoutHolds()->create([
                ...$validated,
                'expires_at' => $expiresAt,
            ]);

            return (string) $hold->getKey();
        }, attempts: 3);
    }

    public function release(string $affiliateId, string $holdId, ?string $notes = null): void
    {
        $this->connection()->transaction(function () use ($affiliateId, $holdId, $notes): void {
            $affiliate = $this->lockAffiliate($affiliateId);
            $hold = $affiliate->payoutHolds()->lockForUpdate()->findOrFail($holdId);

            if ($hold->released_at !== null) {
                return;
            }

            $hold->update([
                'released_at' => CarbonImmutable::now(),
                'notes' => $notes ?? $hold->notes,
            ]);
        }, attempts: 3);
    }

    private function connection(): Connection
    {
        $connection = (new Affiliate)->getConnection();

        if ((new AffiliatePayoutHold)->getConnection() !== $connection) {
            throw new LogicException('Payout hold updates require a shared database connection.');
        }

        return $connection;
    }

    private function lockAffiliate(string $affiliateId): Affiliate
    {
        if ((bool) config('affiliates.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(Affiliate::class, $affiliateId);
        }

        return Affiliate::query()->lockForUpdate()->findOrFail($affiliateId);
    }
}
