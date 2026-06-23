<?php

declare(strict_types=1);

namespace AIArmada\Communications\Console\Commands;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Actions\ApplyProviderEventAction;
use AIArmada\Communications\Contracts\CommunicationAuditRecorder;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationEvent;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Throwable;

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

    public function __construct(
        private readonly CommunicationAuditRecorder $auditRecorder,
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

        $query->chunkById((int) $this->option('batch'), function ($events) use (&$replayed, &$skipped, &$failed, $bar): void {
            foreach ($events as $event) {
                $result = $this->replayEvent($event);
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

    private function replayEvent(CommunicationEvent $event): string
    {
        if (! in_array($event->event, ApplyProviderEventAction::DELIVERY_EVENTS, true)) {
            return 'skipped';
        }

        /** @var CommunicationDelivery|null $delivery */
        $delivery = CommunicationDelivery::query()->find($event->delivery_id);

        if ($delivery === null) {
            return 'skipped';
        }

        try {
            $delivery->status = ApplyProviderEventAction::EVENT_STATUS_MAP[$event->event];

            $timestampColumn = ApplyProviderEventAction::EVENT_TIMESTAMP_MAP[$event->event];

            if ($delivery->{$timestampColumn} === null || $this->option('force')) {
                $delivery->{$timestampColumn} = CarbonImmutable::now();
            }

            $delivery->save();

            $event->processed_at = CarbonImmutable::now();
            $event->save();

            $this->auditRecorder->recordWebhookReplay(
                communicationId: $event->communication_id ?? 'unknown',
                actorType: 'system',
                actorId: 'replay-command',
                reason: 'Webhook event replayed via command',
                metadata: [
                    'event_id' => $event->id,
                    'delivery_id' => $event->delivery_id,
                    'provider' => $event->provider,
                    'event_type' => $event->event,
                    'replayed_at' => CarbonImmutable::now()->toIso8601String(),
                ],
            );

            return 'replayed';
        } catch (Throwable $e) {
            $event->failed_at = CarbonImmutable::now();
            $event->failure_message = $e->getMessage();
            $event->save();

            return 'failed';
        }
    }
}
