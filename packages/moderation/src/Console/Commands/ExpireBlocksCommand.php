<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Console\Commands;

use AIArmada\Moderation\Actions\ExpireModerationBlocksAction;
use Illuminate\Console\Command;

final class ExpireBlocksCommand extends Command
{
    protected $signature = 'moderation:expire-blocks';

    protected $description = 'Expire moderation blocks whose expiry time has passed.';

    public function handle(ExpireModerationBlocksAction $action): int
    {
        $expired = $action->execute();

        $this->info("Expired {$expired} moderation block(s).");

        return self::SUCCESS;
    }
}
