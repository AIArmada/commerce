<?php

declare(strict_types=1);

namespace AIArmada\Chip\Testing;

use AIArmada\Chip\Enums\WebhookEventType;

/**
 * Trait for simulating CHIP webhooks in PHPUnit/Pest tests.
 *
 * Provides convenient methods that delegate to WebhookSimulator.
 * Use this trait in test classes for a cleaner API.
 */
trait SimulatesWebhooks
{
    /**
     * Create a webhook simulator.
     */
    protected function simulateWebhook(): WebhookSimulator
    {
        return WebhookSimulator::make();
    }

    /**
     * Create a paid webhook simulator.
     */
    protected function simulatePaidWebhook(?string $url = null): WebhookSimulator
    {
        $simulator = WebhookSimulator::paid();

        return $url ? $simulator->to($url) : $simulator;
    }

    /**
     * Create a failed webhook simulator.
     */
    protected function simulateFailedWebhook(?string $url = null): WebhookSimulator
    {
        $simulator = WebhookSimulator::failed();

        return $url ? $simulator->to($url) : $simulator;
    }

    /**
     * Create a cancelled webhook simulator.
     */
    protected function simulateCancelledWebhook(?string $url = null): WebhookSimulator
    {
        $simulator = WebhookSimulator::cancelled();

        return $url ? $simulator->to($url) : $simulator;
    }

    /**
     * Create a refunded webhook simulator.
     */
    protected function simulateRefundedWebhook(?string $url = null): WebhookSimulator
    {
        $simulator = WebhookSimulator::refunded();

        return $url ? $simulator->to($url) : $simulator;
    }

    /**
     * Create a webhook simulator for a specific event type.
     */
    protected function simulateWebhookEvent(WebhookEventType $eventType, ?string $url = null): WebhookSimulator
    {
        $simulator = WebhookSimulator::forEvent($eventType);

        return $url ? $simulator->to($url) : $simulator;
    }

    /**
     * Dispatch a webhook event directly without HTTP.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function dispatchWebhookEvent(WebhookEventType $eventType, array $overrides = []): void
    {
        WebhookSimulator::forEvent($eventType)->with($overrides)->dispatch();
    }

    /**
     * Fake webhook events for assertions.
     *
     * @param  array<class-string>|null  $eventsToFake
     */
    protected function fakeWebhookEvents(?array $eventsToFake = null): void
    {
        WebhookSimulator::fakeEvents($eventsToFake);
    }

    /**
     * Assert a webhook event was dispatched.
     *
     * @param  class-string  $eventClass
     */
    protected function assertWebhookDispatched(string $eventClass, ?callable $callback = null): void
    {
        WebhookSimulator::assertDispatched($eventClass, $callback);
    }

    /**
     * Assert a webhook event was not dispatched.
     *
     * @param  class-string  $eventClass
     */
    protected function assertWebhookNotDispatched(string $eventClass): void
    {
        WebhookSimulator::assertNotDispatched($eventClass);
    }

    /**
     * Disable webhook signature verification.
     */
    protected function withoutWebhookSignatureVerification(): self
    {
        WebhookSimulator::withoutSignatureVerification();

        return $this;
    }

    /**
     * Post a webhook to a URL using Laravel's test client.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @return \Illuminate\Testing\TestResponse
     */
    protected function postWebhook(string $url, array $payload, array $headers = [])
    {
        return $this->postJson($url, $payload, array_merge([
            'X-Chip-Event' => $payload['event_type'] ?? 'purchase.paid',
        ], $headers));
    }
}
