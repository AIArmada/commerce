<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Console\Commands;

use AIArmada\Cashier\Actions\SyncWebhook;
use AIArmada\Cashier\Exceptions\GatewayRetrievalException;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Cashier\Gateways\StripeGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class WebhookReplayCommand extends Command
{
    protected $signature = 'cashier:webhook:replay
                          {event-id? : Webhook event ID to replay}
                          {--gateway=stripe : Gateway to replay the webhook for (stripe, chip)}
                          {--dry-run : Dry run without dispatching}';

    protected $description = 'Replay a webhook event by re-fetching it from the gateway API';

    public function handle(GatewayManager $gatewayManager, SyncWebhook $syncWebhook): int
    {
        $eventId = $this->argument('event-id');
        $gateway = (string) ($this->option('gateway') ?: 'stripe');
        $dryRun = (bool) $this->option('dry-run');

        if (! $gatewayManager->supportsGateway($gateway)) {
            $this->error("Gateway [{$gateway}] is not supported. Supported gateways: " . implode(', ', $gatewayManager->supportedGateways()) . '.');

            return self::FAILURE;
        }

        if (! is_string($eventId) || $eventId === '') {
            $this->error('An event ID is required. Replay-all was removed: cashier stores no pending-webhook queue, so pass the gateway event ID to re-fetch.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info("Dry run: would replay webhook event [{$eventId}] from gateway [{$gateway}].");

            return self::SUCCESS;
        }

        try {
            $payload = $this->fetchEventPayload($gatewayManager, $gateway, $eventId);
        } catch (GatewayRetrievalException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // Events re-fetched from the gateway API over TLS are trusted
        // server-side truth: they carry no HTTP signature to verify.
        $syncWebhook->handle($gateway, $payload);

        $this->info("Webhook event [{$eventId}] replayed.");

        Log::info('Webhook replay completed', [
            'event_id' => $eventId,
            'gateway' => $gateway,
        ]);

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchEventPayload(GatewayManager $gatewayManager, string $gateway, string $eventId): array
    {
        $gatewayInstance = $gatewayManager->gateway($gateway);

        if ($gatewayInstance instanceof StripeGateway) {
            return $gatewayInstance->fetchWebhookEvent($eventId);
        }

        throw GatewayRetrievalException::create(
            $gateway,
            'webhook event',
            $eventId,
            new RuntimeException("Gateway [{$gateway}] exposes no event API, so replay-by-ID is unsupported; re-deliver the webhook from the gateway dashboard instead."),
        );
    }
}
