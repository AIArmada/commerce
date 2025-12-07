<?php

declare(strict_types=1);

namespace Tests\Chip\Fixtures;

use AIArmada\Chip\Data\Purchase;
use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Testing\WebhookFactory;
use RuntimeException;

trait WebhookFixtures
{
    /**
     * Create a new webhook factory for fluent payload building.
     */
    protected function webhookFactory(): WebhookFactory
    {
        return WebhookFactory::make();
    }

    /**
     * Get the raw webhook payload for a paid purchase.
     *
     * @return array<string, mixed>
     */
    protected function getWebhookPurchasePaidPayload(): array
    {
        return $this->loadFixture('webhook-purchase-paid.json');
    }

    /**
     * Get the webhook payload with custom overrides.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function getWebhookPurchasePaidPayloadWith(array $overrides): array
    {
        return array_replace_recursive($this->getWebhookPurchasePaidPayload(), $overrides);
    }

    /**
     * Create a Purchase data object from the paid webhook fixture.
     */
    protected function getPurchaseFromPaidWebhook(): Purchase
    {
        return Purchase::fromArray($this->getWebhookPurchasePaidPayload());
    }

    /**
     * Create a WebhookReceived event from the paid webhook fixture.
     */
    protected function getWebhookReceivedEventFromPaidPayload(): WebhookReceived
    {
        return WebhookReceived::fromPayload($this->getWebhookPurchasePaidPayload());
    }

    /**
     * Create a WebhookReceived event with custom overrides.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function getWebhookReceivedEventWith(array $overrides): WebhookReceived
    {
        return WebhookReceived::fromPayload(
            $this->getWebhookPurchasePaidPayloadWith($overrides)
        );
    }

    /**
     * Load a fixture file and decode as array.
     *
     * @return array<string, mixed>
     */
    protected function loadFixture(string $filename): array
    {
        $path = __DIR__.'/'.$filename;

        if (! file_exists($path)) {
            throw new RuntimeException("Fixture file not found: {$path}");
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("Could not read fixture file: {$path}");
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON in fixture file: {$path}");
        }

        return $decoded;
    }
}
