<?php

declare(strict_types=1);

namespace AIArmada\Communications\Console\Commands;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Models\Communication;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

final class DispatchDueCommunicationsCommand extends Command
{
    protected $signature = 'communications:dispatch-due
        {--owner= : Owner morph class and ID (e.g., "App\Models\Team:1")}
        {--dry-run : List due communications without dispatching}
        {--batch=100 : Number of communications to process per batch}';

    protected $description = 'Dispatch scheduled communications that are due';

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
            return null; // explicit global
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
        $query = Communication::query()
            ->where('status', CommunicationStatus::Scheduled->value)
            ->where('scheduled_at', '<=', CarbonImmutable::now())
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', CarbonImmutable::now());
            });

        $count = $query->count();

        if ($count === 0) {
            $this->info('No due communications found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Found {$count} due communications (dry-run, not dispatched).");

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->chunk((int) $this->option('batch'), function ($communications) use ($bar): void {
            foreach ($communications as $communication) {
                $communication->status = CommunicationStatus::Queued;
                $communication->queued_at = CarbonImmutable::now();
                $communication->save();

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Dispatched {$count} communications.");

        return self::SUCCESS;
    }
}
