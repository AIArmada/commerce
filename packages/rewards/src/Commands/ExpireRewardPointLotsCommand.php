<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Commands;

use AIArmada\Rewards\Actions\ExpireDueRewardPointLotsAction;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

final class ExpireRewardPointLotsCommand extends Command
{
    protected $signature = 'rewards:expire-point-lots {--as-of= : Expiry timestamp (UTC, defaults to now)}';

    protected $description = 'Expire due point lots and release their reservations as expiry movements.';

    public function handle(ExpireDueRewardPointLotsAction $expire): int
    {
        $asOf = $this->option('as-of');
        $at = CarbonImmutable::now('UTC');

        if (is_string($asOf) && $asOf !== '') {
            try {
                $at = CarbonImmutable::parse($asOf)->setTimezone('UTC');
            } catch (Throwable) {
                $this->error('Expiry timestamp (--as-of) must be parseable.');

                return self::INVALID;
            }
        }

        $result = $expire->handle($at);
        $movements = $result['movements'];
        $scopeCount = $result['scopes'];

        $this->info("Expired {$movements} lot movements across {$scopeCount} scopes.");

        return self::SUCCESS;
    }
}
