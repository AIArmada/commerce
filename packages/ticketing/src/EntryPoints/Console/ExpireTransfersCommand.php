<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\EntryPoints\Console;

use AIArmada\Ticketing\Actions\ExpireTransfersAction;
use Illuminate\Console\Command;

final class ExpireTransfersCommand extends Command
{
    protected $signature = 'ticketing:expire-transfers';

    protected $description = 'Expire transfer windows for passes past their transfer_expires_at';

    public function handle(ExpireTransfersAction $action): int
    {
        $count = $action->handle();

        $this->info("Expired {$count} transfer windows.");

        return self::SUCCESS;
    }
}
