<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Actions;

use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\States\Revoked;

final readonly class RevokePassAction
{
    public function handle(Pass $pass, ?string $reason = null): Pass
    {
        return $pass->getConnection()->transaction(function () use ($pass, $reason): Pass {
            $locked = Pass::query()->whereKey($pass->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->status instanceof Revoked) {
                $locked->markRevoked($reason);
            }

            return $locked;
        });
    }
}
