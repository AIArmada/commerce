<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Services;

use AIArmada\Ticketing\Contracts\PassTransferServiceInterface;
use AIArmada\Ticketing\Events\PassTransferred;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;
use AIArmada\Ticketing\Models\PassTransfer;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class DefaultPassTransferService implements PassTransferServiceInterface
{
    public function transfer(Pass $pass, PassHolder $newHolder, ?string $reason = null): PassHolder
    {
        if (! $this->canTransfer($pass)) {
            throw new RuntimeException('Pass cannot be transferred in its current state.');
        }

        $this->assertHolderBelongsToPass($pass, $newHolder);

        return DB::transaction(function () use ($pass, $newHolder, $reason) {
            $freshPass = Pass::query()->whereKey($pass->getKey())->lockForUpdate()->first();

            if (! $freshPass instanceof Pass || ! $this->canTransfer($freshPass)) {
                throw new RuntimeException('Pass cannot be transferred in its current state.');
            }

            $previousHolder = $freshPass->holder()->lockForUpdate()->first();

            if ($previousHolder !== null) {
                $previousHolder->is_current = false;
                $previousHolder->transferred_at = CarbonImmutable::now();
                $previousHolder->save();
            }

            $newHolder->pass_id = $pass->getKey();
            $newHolder->is_current = true;
            $newHolder->save();

            $transfer = new PassTransfer;
            $transfer->pass_id = $pass->getKey();
            $transfer->from_holder_id = $previousHolder?->getKey();
            $transfer->to_holder_id = $newHolder->getKey();
            $transfer->reason = $reason;
            $transfer->save();

            event(new PassTransferred($pass, $previousHolder ?? $newHolder, $newHolder, $reason));

            return $newHolder;
        });
    }

    public function canTransfer(Pass $pass): bool
    {
        if (! $pass->isValid()) {
            return false;
        }

        if ($pass->transfer_expires_at !== null && CarbonImmutable::now()->isAfter($pass->transfer_expires_at)) {
            return false;
        }

        return true;
    }

    private function assertHolderBelongsToPass(Pass $pass, PassHolder $newHolder): void
    {
        if (! $newHolder->exists) {
            return;
        }

        if ($newHolder->pass_id !== null && (string) $newHolder->pass_id !== (string) $pass->getKey()) {
            throw new InvalidArgumentException('The holder already belongs to a different pass.');
        }

        if (config('ticketing.owner.enabled', true)
            && ($newHolder->owner_type !== $pass->owner_type || (string) $newHolder->owner_id !== (string) $pass->owner_id)
        ) {
            throw new AuthorizationException('The holder is not accessible in the current owner scope.');
        }
    }
}
