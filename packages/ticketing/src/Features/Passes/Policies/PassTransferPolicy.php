<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Policies;

use AIArmada\Ticketing\Features\Passes\Models\Pass;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\User;

final class PassTransferPolicy
{
    public function transfer(User $user, Pass $pass): bool
    {
        if (! $pass->isValid()) {
            return false;
        }

        if ($pass->transfer_expires_at !== null && CarbonImmutable::now()->greaterThanOrEqualTo($pass->transfer_expires_at)) {
            return false;
        }

        $holder = $pass->holder;

        if ($holder === null) {
            return false;
        }

        if ($holder->holder_type !== null && $holder->holder_id !== null) {
            return $holder->holder_type === $user->getMorphClass()
                && (string) $holder->holder_id === (string) $user->getKey();
        }

        return false;
    }
}
