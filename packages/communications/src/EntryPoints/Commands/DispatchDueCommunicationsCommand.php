<?php

declare(strict_types=1);

namespace AIArmada\Communications\EntryPoints\Commands;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Features\Aggregate\Api\ScheduledCommunicationLifecycle;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class DispatchDueCommunicationsCommand extends Command
{
    protected $signature = 'communications:dispatch-due
        {--owner= : Owner morph class and ID (e.g., "App\Models\Team:1")}
        {--dry-run : List due communications without dispatching}
        {--batch=100 : Number of communications to process per batch}';

    protected $description = 'Dispatch scheduled communications that are due';

    public function handle(): int
    {
        try {
            return OwnerContext::withOwner(
                owner: $this->resolveOwner(),
                callback: fn (): int => $this->process(),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function resolveOwner(): ?Model
    {
        $owner = $this->option('owner');

        if ($owner === null) {
            return null; // explicit global
        }

        if (str_contains($owner, ':')) {
            [$type, $id] = explode(':', $owner, 2);

            return OwnerContext::fromTypeAndId($type, $id);
        }

        throw new InvalidArgumentException('Invalid --owner format. Use "TypeClass:id" (e.g. "App\Models\Team:1").');
    }

    private function process(): int
    {
        $lifecycle = app(ScheduledCommunicationLifecycle::class);
        $count = $lifecycle->countDue();

        if ($count === 0) {
            $this->info('No due communications found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Found {$count} due communications (dry-run, not dispatched).");

            return self::SUCCESS;
        }

        $dispatched = 0;
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($lifecycle->dueIds(max(1, (int) $this->option('batch'))) as $id) {
            if ($lifecycle->dispatch($id)) {
                $dispatched++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Dispatched {$dispatched} communications.");

        return self::SUCCESS;
    }
}
