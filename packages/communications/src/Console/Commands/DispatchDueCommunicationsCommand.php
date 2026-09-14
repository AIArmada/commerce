<?php

declare(strict_types=1);

namespace AIArmada\Communications\Console\Commands;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Jobs\DispatchCommunicationDeliveriesJob;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
        $query = Communication::query()
            ->where('status', CommunicationStatus::Scheduled->value)
            ->where('scheduled_at', '<=', CarbonImmutable::now())
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')
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

        $batchSize = max(1, (int) $this->option('batch'));

        $query->chunkById($batchSize, function (Collection $communications) use ($bar): void {
            $now = CarbonImmutable::now();
            $communicationIds = [];

            foreach ($communications as $communication) {
                $communication->status = CommunicationStatus::Queued;
                $communication->queued_at = $now;
                $communication->save();

                $communicationIds[] = $communication->id;
            }

            CommunicationDelivery::query()
                ->whereIn('communication_id', $communicationIds)
                ->whereIn('status', [DeliveryStatus::Pending, DeliveryStatus::Scheduled])
                ->where(function (Builder $query) use ($now): void {
                    $query->whereNull('scheduled_at')
                        ->orWhere('scheduled_at', '<=', $now);
                })
                ->update([
                    'status' => DeliveryStatus::Queued->value,
                    'queued_at' => $now,
                ]);

            foreach ($communications as $communication) {
                DispatchCommunicationDeliveriesJob::dispatch(
                    communicationId: $communication->id,
                    ownerType: $communication->owner_type,
                    ownerId: $communication->owner_id,
                    ownerIsGlobal: $communication->owner_type === null && $communication->owner_id === null,
                )->afterCommit();

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Dispatched {$count} communications.");

        return self::SUCCESS;
    }
}
