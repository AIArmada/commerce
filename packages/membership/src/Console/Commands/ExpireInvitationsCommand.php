<?php

declare(strict_types=1);

namespace AIArmada\Membership\Console\Commands;

use AIArmada\Membership\Actions\ExpireMembershipInvitationsAction;
use Illuminate\Console\Command;

final class ExpireInvitationsCommand extends Command
{
    protected $signature = 'membership:expire-invitations';

    protected $description = 'Expire membership invitations whose expiry time has passed.';

    public function handle(ExpireMembershipInvitationsAction $action): int
    {
        $expired = $action->execute();

        $this->info("Expired {$expired} membership invitation(s).");

        return self::SUCCESS;
    }
}
