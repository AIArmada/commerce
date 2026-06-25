<?php

declare(strict_types=1);

namespace AIArmada\Communications\Console\Commands;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Models\CommunicationAttempt;
use AIArmada\Communications\Models\CommunicationEvent;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class PruneCommunicationDataCommand extends Command
{
    protected $signature = 'communications:prune
        {--owner= : Owner morph class and ID (e.g., "App\Models\Team:1")}
        {--before= : Prune data older than this date (default: config retention)}
        {--dry-run : Report what would be pruned without deleting}';

    protected $description = 'Prune old communication data according to retention policy';

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
            return null;
        }

        if (str_contains($owner, ':')) {
            [$type, $id] = explode(':', $owner, 2);

            return OwnerContext::fromTypeAndId($type, $id);
        }

        throw new InvalidArgumentException('Invalid --owner format. Use "TypeClass:id" (e.g. "App\Models\Team:1").');
    }

    private function process(): int
    {
        $retentionDays = config('communications.logging.payload_retention_days', 90);
        $before = $this->option('before')
            ? CarbonImmutable::parse($this->option('before'))
            : CarbonImmutable::now()->subDays($retentionDays);

        $this->info("Pruning data before: {$before->toDateTimeString()}");

        $attemptQuery = CommunicationAttempt::query()
            ->where('created_at', '<', $before);

        $eventQuery = CommunicationEvent::query()
            ->where('created_at', '<', $before);

        $attemptCount = $attemptQuery->count();
        $eventCount = $eventQuery->count();

        if ($this->option('dry-run')) {
            $this->info("Would prune {$attemptCount} attempts and {$eventCount} events.");

            return self::SUCCESS;
        }

        $attemptQuery->delete();
        $eventQuery->delete();

        $this->info("Pruned {$attemptCount} attempts and {$eventCount} events.");

        return self::SUCCESS;
    }
}
