<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Console;

use AIArmada\FilamentAuthz\Services\PermissionCacheService;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;

class AuthzCacheCommand extends Command
{
    protected $signature = 'authz:cache
        {action? : The action to perform (flush, warm, stats)}';

    protected $description = 'Manage permission caches';

    public function handle(PermissionCacheService $cacheService): int
    {
        $action = $this->argument('action') ?? select(
            label: 'What would you like to do?',
            options: [
                'flush' => 'Flush all permission caches',
                'warm' => 'Warm permission caches',
                'stats' => 'Show cache statistics',
            ]
        );

        return match ($action) {
            'flush' => $this->flushCache($cacheService),
            'warm' => $this->warmCache($cacheService),
            'stats' => $this->showStats($cacheService),
            default => self::FAILURE,
        };
    }

    protected function flushCache(PermissionCacheService $cacheService): int
    {
        if (confirm('This will flush all permission caches. Continue?', false)) {
            $cacheService->flush();
            info('Permission caches flushed successfully.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    protected function warmCache(PermissionCacheService $cacheService): int
    {
        info('Warming role caches...');
        $cacheService->warmRoleCache();

        info('Cache warming complete.');

        return self::SUCCESS;
    }

    protected function showStats(PermissionCacheService $cacheService): int
    {
        $stats = $cacheService->getStats();

        table(
            ['Setting', 'Value'],
            [
                ['Enabled', $stats['enabled'] ? 'Yes' : 'No'],
                ['Store', $stats['store']],
                ['TTL (seconds)', (string) $stats['ttl']],
            ]
        );

        return self::SUCCESS;
    }
}
