<?php

declare(strict_types=1);

namespace AIArmada\Authz\Support;

use AIArmada\Authz\Console\Commands\SuperAdminCommand;
use AIArmada\Authz\Console\Commands\SyncAuthzCommand;

final class CommandProhibitor
{
    /**
     * @var array<class-string, true>
     */
    private static array $commands = [];

    private static bool $prohibited = false;

    /**
     * Register package commands that should follow the shared prohibition.
     *
     * @param  list<class-string>  $commands
     */
    public static function register(array $commands): void
    {
        foreach ($commands as $command) {
            self::$commands[$command] = true;

            if (is_callable([$command, 'prohibit'])) {
                $command::prohibit(self::$prohibited);
            }
        }
    }

    public static function prohibitDestructiveCommands(bool $prohibit = true): void
    {
        self::$prohibited = $prohibit;
        self::register([
            SuperAdminCommand::class,
            SyncAuthzCommand::class,
        ]);

        foreach (array_keys(self::$commands) as $command) {
            if (is_callable([$command, 'prohibit'])) {
                $command::prohibit($prohibit);
            }
        }
    }
}
