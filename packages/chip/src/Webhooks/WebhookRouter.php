<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Services\WebhookEventDispatcher;
use AIArmada\Chip\Webhooks\Handlers\SendCompletedHandler;
use AIArmada\Chip\Webhooks\Handlers\SendRejectedHandler;
use AIArmada\Chip\Webhooks\Handlers\WebhookHandler;

/**
 * Routes webhook events to appropriate handlers.
 */
class WebhookRouter
{
    public function __construct(
        private readonly ?WebhookEventDispatcher $dispatcher = null,
    ) {}

    /**
     * @var array<string, class-string<WebhookHandler>>
     */
    protected array $handlers = [
        'send_instruction.completed' => SendCompletedHandler::class,
        'send_instruction.rejected' => SendRejectedHandler::class,
        'payout.success' => SendCompletedHandler::class,
        'payout.failed' => SendRejectedHandler::class,
    ];

    /**
     * Route the webhook to the appropriate handler.
     */
    public function route(string $event, EnrichedWebhookPayload $payload): WebhookResult
    {
        $handlerClass = $this->handlers[$event] ?? null;

        if ($handlerClass !== null) {
            /** @var WebhookHandler $handler */
            $handler = app($handlerClass);

            return $handler->handle($payload);
        }

        if (WebhookEventType::fromString($event) === null) {
            return WebhookResult::skipped("No handler registered for event: {$event}");
        }

        $dispatcher = $this->dispatcher ?? app(WebhookEventDispatcher::class);

        WebhookReceived::dispatch(
            $event,
            $payload->rawPayload,
            $dispatcher->extractPurchase($payload->rawPayload),
            $dispatcher->extractPayout($payload->rawPayload),
            $dispatcher->extractBillingTemplateClient($payload->rawPayload),
            $dispatcher->extractPayment($payload->rawPayload),
        );

        $dispatcher->dispatch($event, $payload->rawPayload);

        return WebhookResult::handled("Webhook {$event} replayed through the dispatcher");
    }

    /**
     * Register a custom handler for an event.
     *
     * @param  class-string<WebhookHandler>  $handlerClass
     */
    public function registerHandler(string $event, string $handlerClass): self
    {
        $this->handlers[$event] = $handlerClass;

        return $this;
    }

    /**
     * Check if a handler exists for the event.
     */
    public function hasHandler(string $event): bool
    {
        return isset($this->handlers[$event]) || WebhookEventType::fromString($event) !== null;
    }

    /**
     * Get all explicitly registered handlers.
     *
     * @return array<string, class-string<WebhookHandler>>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }
}
