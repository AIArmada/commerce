<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Actions;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutHoldAdministration;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutHold;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Connection;

final readonly class AdministerPayoutHold implements PayoutHoldAdministration
{
    public function __construct(private AffiliateAccessGuard $affiliates) {}

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
            $this->affiliates->lockForWrite($affiliateId, spl_object_id($this->connection()));
            $hold = AffiliatePayoutHold::query()->create([
                'affiliate_id' => $affiliateId,
                ...$validated,
                'expires_at' => $expiresAt,
            ]);

            return (string) $hold->getKey();
        }, attempts: 3);
    }

    public function release(string $affiliateId, string $holdId, ?string $notes = null): void
    {
        $this->connection()->transaction(function () use ($affiliateId, $holdId, $notes): void {
            $this->affiliates->lockForWrite($affiliateId, spl_object_id($this->connection()));
            $hold = AffiliatePayoutHold::query()->where('affiliate_id', $affiliateId)->lockForUpdate()->findOrFail($holdId);

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
        return (new AffiliatePayoutHold)->getConnection();
    }
}
