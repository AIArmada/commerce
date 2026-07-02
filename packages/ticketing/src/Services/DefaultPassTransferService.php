<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Services;

use AIArmada\Ticketing\Contracts\PassTransferServiceInterface;
use AIArmada\Ticketing\Events\PassTransferred;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;
use AIArmada\Ticketing\Models\PassTransfer;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DefaultPassTransferService implements PassTransferServiceInterface
{
    public function transfer(Pass $pass, PassHolder $newHolder, ?string $reason = null): PassHolder
    {
        if (! $this->canTransfer($pass)) {
            throw new RuntimeException('Pass cannot be transferred in its current state.');
        }

        return DB::transaction(function () use ($pass, $newHolder, $reason) {
            $previousHolder = $pass->holder;

            if ($previousHolder !== null) {
                $previousHolder->is_current = false;
                $previousHolder->transferred_at = now();
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

        if ($pass->transfer_expires_at !== null && now()->isAfter($pass->transfer_expires_at)) {
            return false;
        }

        return true;
    }
}
