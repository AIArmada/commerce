<?php

declare(strict_types=1);

namespace AIArmada\Membership\Console\Commands;

use AIArmada\Membership\Services\MembershipRoleSyncService;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

final class SyncRolesCommand extends Command
{
    protected $signature = 'membership:sync-roles
        {--flush-cache : Flush permission cache after sync}';

    protected $description = 'Sync membership roles into Spatie permission roles.';

    public function handle(MembershipRoleSyncService $syncService): int
    {
        $count = $syncService->syncAll();

        if ($this->option('flush-cache')) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->info('Permission cache flushed.');
        }

        $this->info("Synced {$count} membership roles.");

        return self::SUCCESS;
    }
}
