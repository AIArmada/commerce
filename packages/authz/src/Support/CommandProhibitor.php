<?php

declare(strict_types=1);

namespace AIArmada\Authz\Support;

use AIArmada\Authz\Console\Commands\SuperAdminCommand;
use AIArmada\Authz\Console\Commands\SyncAuthzCommand;
use AIArmada\FilamentAuthz\Console\GeneratePoliciesCommand;
use AIArmada\FilamentAuthz\Console\SeederCommand;

final class CommandProhibitor
{
    public static function prohibitDestructiveCommands(bool $prohibit = true): void
    {
        if (class_exists(GeneratePoliciesCommand::class)) {
            GeneratePoliciesCommand::prohibit($prohibit);
        }

        if (class_exists(SeederCommand::class)) {
            SeederCommand::prohibit($prohibit);
        }

        SuperAdminCommand::prohibit($prohibit);
        SyncAuthzCommand::prohibit($prohibit);
    }
}
