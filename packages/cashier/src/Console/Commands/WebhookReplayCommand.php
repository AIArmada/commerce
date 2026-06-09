<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Console\Commands;

use AIArmada\Cashier\Actions\SyncWebhook;
use AIArmada\Cashier\GatewayManager;
use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\Customers\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class WebhookReplayCommand extends Command
{
    protected $signature = 'cashier:webhook:replay
                          {event-id? : Specific webhook event ID to replay (replays all if omitted)}
                          {--gateway= : Gateway to replay webhooks for (stripe, chip)}
                          {--dry-run : Dry run without dispatching}';

    protected $description = 'Replay failed or pending webhook events';

    public function handle(GatewayManager $gatewayManager, SyncWebhook $syncWebhook): int
    {
        $eventId = $this->argument('event-id');
        $gateway = $this->option('gateway');
        $dryRun = (bool) $this->option('dry-run');

        if (is_string($eventId) && $eventId !== '') {
            $this->info("Replaying webhook event: {$eventId}");

            if (! $dryRun) {
                $syncWebhook->handle($gateway ?? 'stripe', ['event_id' => $eventId]);
            }

            $this->info('Webhook event replayed.');

            return self::SUCCESS;
        }

        $runner = new OwnerBatchRunner(Customer::class, [
            'enabled' => 'commerce-support.owner.enabled',
        ]);

        $this->info('Replaying all pending webhook events...');

        $total = $runner->run(function () use ($gatewayManager, $syncWebhook, $dryRun, $gateway): int {
            $processed = 0;

            if ($dryRun) {
                return 0;
            }

            $gateways = is_string($gateway) && $gateway !== ''
                ? [$gateway]
                : ['stripe', 'chip'];

            foreach ($gateways as $gw) {
                $manager = $gatewayManager->gateway($gw);

                if (method_exists($manager, 'pendingWebhooks')) {
                    $pending = $manager->pendingWebhooks();

                    foreach ($pending as $webhookEvent) {
                        $syncWebhook->handle($gw, (array) $webhookEvent);
                        $processed++;
                    }
                }
            }

            return $processed;
        });

        $this->info("Webhook events processed: {$total}");

        Log::info('Webhook replay completed', [
            'total' => $total,
            'gateway' => $gateway,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }
}
