<?php

declare(strict_types=1);

namespace AIArmada\Communications\EntryPoints\Commands;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Features\Delivery\Api\WebhookReplay;
use AIArmada\Communications\Features\Delivery\Models\CommunicationEvent;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class ReplayWebhookEventsCommand extends Command
{
    protected $signature = 'communications:replay-webhooks
        {--owner= : Owner morph class and ID (e.g., "App\Models\Team:1")}
        {--provider= : Only replay events from this provider}
        {--communication= : Only replay events for this communication ID}
        {--delivery= : Only replay events for this delivery ID}
        {--force : Replay already-processed events}
        {--dry-run : Report what would be replayed without modifying}
        {--batch=100 : Number of events to process per batch}';

    protected $description = 'Replay webhook events to re-apply delivery status updates';

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
        $query = CommunicationEvent::query()
            ->whereNotNull('delivery_id');

        if (! $this->option('force')) {
            $query->whereNull('processed_at');
        }

        if ($provider = $this->option('provider')) {
            $query->where('provider', $provider);
        }

        if ($communicationId = $this->option('communication')) {
            $query->where('communication_id', $communicationId);
        }

        if ($deliveryId = $this->option('delivery')) {
            $query->where('delivery_id', $deliveryId);
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No webhook events to replay.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $mode = $this->option('force') ? 'replay (force)' : 'replay';
            $this->info("Found {$count} webhook events to {$mode} (dry-run).");

            return self::SUCCESS;
        }

        $replayed = 0;
        $skipped = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->chunkById(max(1, (int) $this->option('batch')), function ($events) use (&$replayed, &$skipped, &$failed, $bar): void {
            foreach ($events as $event) {
                $result = app(WebhookReplay::class)->replay($event->id, (bool) $this->option('force'));
                match ($result) {
                    'replayed' => $replayed++,
                    'skipped' => $skipped++,
                    'failed' => $failed++,
                    default => null,
                };
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("Replayed {$replayed}, skipped {$skipped}, failed {$failed} webhook events.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

}
