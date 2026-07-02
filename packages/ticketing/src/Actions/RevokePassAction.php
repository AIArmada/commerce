<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Actions;

use AIArmada\Ticketing\Models\Pass;
use Lorisleiva\Actions\Concerns\AsAction;

final class RevokePassAction
{
    use AsAction;

    public function handle(Pass $pass, ?string $reason = null): Pass
    {
        $pass->markRevoked($reason);
        $pass->status_reason = $reason;
        $pass->save();

        return $pass;
    }
}
