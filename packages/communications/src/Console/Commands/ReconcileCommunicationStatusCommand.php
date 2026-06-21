<?php

declare(strict_types=1);

namespace AIArmada\Communications\Console\Commands;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Actions\RecalculateCommunicationStatusAction;
use AIArmada\Communications\Models\Communication;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

final class ReconcileCommunicationStatusCommand extends Command
{
    protected $signature = 'communications:reconcile
        {--owner= : Owner morph class and ID (e.g., "App\Models\Team:1")}
        {--status= : Only reconcile communications with this status}
        {--chunk=100 : Number of communications to process per batch}
        {--dry-run : Report what would be reconciled without modifying}';

    protected $description = 'Reconcile communication aggregate statuses with deliveries';

    public function __construct(
        private readonly RecalculateCommunicationStatusAction $recalculateAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        return OwnerContext::withOwner(
            owner: $this->resolveOwner(),
            callback: fn (): int => $this->process(),
        );
    }

    private function resolveOwner(): ?Model
    {
        $owner = $this->option('owner');

        if ($owner === null) {
            return null;
        }

        if (str_contains($owner, ':')) {
            [$type, $id] = explode(':', $owner, 2);

            return OwnerContext::fromTypeAndId($type, $id);
        }

        $this->error('Invalid --owner format. Use "TypeClass:id" (e.g. "App\Models\Team:1").');

        exit(self::FAILURE);
    }

    private function process(): int
    {
        $query = Communication::query();

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No communications to reconcile.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Found {$count} communications to reconcile (dry-run).");

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->chunkById((int) $this->option('chunk'), function ($communications) use ($bar): void {
            foreach ($communications as $communication) {
                $this->recalculateAction->handle($communication->id);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Reconciled {$count} communications.");

        return self::SUCCESS;
    }
}
