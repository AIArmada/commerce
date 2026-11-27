<?php

declare(strict_types=1);

namespace AIArmada\Communications\EntryPoints\Commands;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Features\Delivery\Api\WebhookReplay;
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
        $replay = app(WebhookReplay::class);
        $force = (bool) $this->option('force');
        $provider = $this->option('provider');
        $communicationId = $this->option('communication');
        $deliveryId = $this->option('delivery');
        $count = $replay->count($force, $provider, $communicationId, $deliveryId);

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

        foreach ($replay->ids($force, $provider, $communicationId, $deliveryId, max(1, (int) $this->option('batch'))) as $eventId) {
            match ($replay->replay($eventId, $force)) {
                'replayed' => $replayed++,
                'skipped' => $skipped++,
                'failed' => $failed++,
            };
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Replayed {$replayed}, skipped {$skipped}, failed {$failed} webhook events.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
