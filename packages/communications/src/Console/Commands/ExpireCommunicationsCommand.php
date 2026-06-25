<?php

declare(strict_types=1);

namespace AIArmada\Communications\Console\Commands;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Models\Communication;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class ExpireCommunicationsCommand extends Command
{
    protected $signature = 'communications:expire
        {--owner= : Owner morph class and ID (e.g., "App\Models\Team:1")}
        {--dry-run : Report what would be expired without modifying}';

    protected $description = 'Expire communications past their expires_at deadline';

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
        $query = Communication::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', CarbonImmutable::now())
            ->whereNotIn('status', [
                CommunicationStatus::Completed->value,
                CommunicationStatus::Failed->value,
                CommunicationStatus::Cancelled->value,
                CommunicationStatus::Expired->value,
            ]);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No expired communications found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Found {$count} communications to expire (dry-run).");

            return self::SUCCESS;
        }

        $query->update([
            'status' => CommunicationStatus::Expired->value,
            'expires_at' => CarbonImmutable::now(),
        ]);

        $this->info("Expired {$count} communications.");

        return self::SUCCESS;
    }
}
