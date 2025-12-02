<?php

declare(strict_types=1);

namespace AIArmada\Chip\Testing;

use AIArmada\Chip\DataObjects\BillingTemplateClient;
use AIArmada\Chip\DataObjects\Payout;
use AIArmada\Chip\DataObjects\Purchase;
use AIArmada\Chip\DataObjects\Webhook;
use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\Chip\Events\BillingCancelled;
use AIArmada\Chip\Events\PaymentRefunded;
use AIArmada\Chip\Events\PayoutFailed;
use AIArmada\Chip\Events\PayoutPending;
use AIArmada\Chip\Events\PayoutSuccess;
use AIArmada\Chip\Events\PurchaseCancelled;
use AIArmada\Chip\Events\PurchaseCaptured;
use AIArmada\Chip\Events\PurchaseCreated;
use AIArmada\Chip\Events\PurchaseHold;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\PurchasePaymentFailure;
use AIArmada\Chip\Events\PurchasePendingCapture;
use AIArmada\Chip\Events\PurchasePendingCharge;
use AIArmada\Chip\Events\PurchasePendingExecute;
use AIArmada\Chip\Events\PurchasePendingRecurringTokenDelete;
use AIArmada\Chip\Events\PurchasePendingRefund;
use AIArmada\Chip\Events\PurchasePendingRelease;
use AIArmada\Chip\Events\PurchasePreauthorized;
use AIArmada\Chip\Events\PurchaseRecurringTokenDeleted;
use AIArmada\Chip\Events\PurchaseReleased;
use AIArmada\Chip\Events\PurchaseSubscriptionChargeFailure;
use AIArmada\Chip\Events\WebhookReceived;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Comprehensive CHIP webhook simulator for testing.
 *
 * Supports both HTTP POST simulation and direct event dispatching.
 * Can be used standalone or via the SimulatesWebhooks trait.
 */
final class WebhookSimulator
{
    private WebhookFactory $factory;

    private ?string $url = null;

    /** @var array<string, string> */
    private array $headers = [];

    private int $timeout = 30;

    public function __construct(?WebhookFactory $factory = null)
    {
        $this->factory = $factory ?? WebhookFactory::make();
    }

    public static function make(): self
    {
        return new self;
    }

    /**
     * Create simulator for a specific event type.
     */
    public static function forEvent(WebhookEventType $eventType): self
    {
        return (new self)->factory(
            WebhookFactory::make()->eventType($eventType->value)
        );
    }

    /**
     * Create simulator with a paid webhook.
     */
    public static function paid(): self
    {
        return (new self)->factory(WebhookFactory::make()->paid());
    }

    /**
     * Create simulator with a created webhook.
     */
    public static function created(): self
    {
        return (new self)->factory(WebhookFactory::make()->created());
    }

    /**
     * Create simulator with a refunded webhook.
     */
    public static function refunded(): self
    {
        return (new self)->factory(WebhookFactory::make()->refunded());
    }

    /**
     * Create simulator with a cancelled webhook.
     */
    public static function cancelled(): self
    {
        return (new self)->factory(WebhookFactory::make()->cancelled());
    }

    /**
     * Create simulator with an expired webhook.
     */
    public static function expired(): self
    {
        return (new self)->factory(WebhookFactory::make()->expired());
    }

    /**
     * Create simulator with a failed webhook.
     */
    public static function failed(): self
    {
        return (new self)->factory(WebhookFactory::make()->failed());
    }

    /**
     * Fake all webhook events for testing assertions.
     *
     * @param  array<class-string>|null  $eventsToFake
     */
    public static function fakeEvents(?array $eventsToFake = null): void
    {
        $events = $eventsToFake ?? [
            WebhookReceived::class,
            PurchaseCreated::class,
            PurchasePaid::class,
            PurchasePaymentFailure::class,
            PurchaseCancelled::class,
            PurchasePreauthorized::class,
            PurchaseHold::class,
            PurchaseCaptured::class,
            PurchaseReleased::class,
            PurchaseSubscriptionChargeFailure::class,
            PaymentRefunded::class,
            BillingCancelled::class,
            PayoutPending::class,
            PayoutSuccess::class,
            PayoutFailed::class,
        ];

        Event::fake($events);
    }

    /**
     * Assert that a webhook event was dispatched.
     *
     * @param  class-string  $eventClass
     */
    public static function assertDispatched(string $eventClass, ?callable $callback = null): void
    {
        Event::assertDispatched($eventClass, $callback);
    }

    /**
     * Assert that a webhook event was not dispatched.
     *
     * @param  class-string  $eventClass
     */
    public static function assertNotDispatched(string $eventClass): void
    {
        Event::assertNotDispatched($eventClass);
    }

    /**
     * Disable webhook signature verification for testing.
     */
    public static function withoutSignatureVerification(): void
    {
        config(['chip.webhooks.verify_signature' => false]);
    }

    /**
     * Set the webhook factory.
     */
    public function factory(WebhookFactory $factory): self
    {
        $this->factory = $factory;

        return $this;
    }

    /**
     * Set the URL to POST to.
     */
    public function to(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Set the webhook URL (alias for to()).
     */
    public function url(string $url): self
    {
        return $this->to($url);
    }

    /**
     * Add a custom header.
     */
    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Add multiple headers.
     *
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * Set request timeout.
     */
    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Configure the underlying factory - amount.
     */
    public function amount(int $amountInCents): self
    {
        $this->factory->amount($amountInCents);

        return $this;
    }

    /**
     * Set the reference for the webhook.
     */
    public function reference(string $reference): self
    {
        $this->factory->reference($reference);

        return $this;
    }

    /**
     * Set the purchase ID.
     */
    public function purchaseId(string $id): self
    {
        $this->factory->purchaseId($id);

        return $this;
    }

    /**
     * Set the client ID.
     */
    public function clientId(string $id): self
    {
        $this->factory->clientId($id);

        return $this;
    }

    /**
     * Set customer details.
     */
    public function customer(string $email, string $name, string $phone = '+60123456789'): self
    {
        $this->factory->customer($email, $name, $phone);

        return $this;
    }

    /**
     * Add a product to the webhook.
     */
    public function addProduct(string $name, int $priceInCents, string $quantity = '1.0000', string $category = 'product'): self
    {
        $this->factory->addProduct($name, $priceInCents, $quantity, $category);

        return $this;
    }

    /**
     * Set the payment method.
     */
    public function paymentMethod(string $method): self
    {
        $this->factory->paymentMethod($method);

        return $this;
    }

    /**
     * Use FPX payment method.
     */
    public function fpx(): self
    {
        $this->factory->fpx();

        return $this;
    }

    /**
     * Use card payment method.
     */
    public function card(): self
    {
        $this->factory->card();

        return $this;
    }

    /**
     * Set as test mode.
     */
    public function isTest(bool $isTest = true): self
    {
        $this->factory->isTest($isTest);

        return $this;
    }

    /**
     * Set as live mode.
     */
    public function live(): self
    {
        $this->factory->live();

        return $this;
    }

    /**
     * Apply custom overrides to the payload.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function with(array $overrides): self
    {
        $this->factory->with($overrides);

        return $this;
    }

    /**
     * Get the webhook payload that will be sent.
     *
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->factory->toArray();
    }

    /**
     * Get the payload as JSON.
     */
    public function getPayloadJson(): string
    {
        return $this->factory->toJson();
    }

    /**
     * Send the webhook via HTTP POST request.
     *
     * @throws RuntimeException If URL is not set
     */
    public function send(): Response
    {
        if ($this->url === null) {
            throw new RuntimeException('Webhook URL not set. Use ->to($url) or ->url($url) to set the target URL.');
        }

        $payload = $this->factory->toArray();

        return Http::timeout($this->timeout)
            ->withHeaders(array_merge([
                'Content-Type' => 'application/json',
                'User-Agent' => 'CHIP-Webhook-Simulator/1.0',
                'X-Chip-Event' => $payload['event_type'] ?? 'purchase.paid',
            ], $this->headers))
            ->post($this->url, $payload);
    }

    /**
     * Send the webhook and assert the response is successful.
     *
     * @throws RuntimeException If response is not successful
     */
    public function sendAndAssertSuccess(): Response
    {
        $response = $this->send();

        if (! $response->successful()) {
            throw new RuntimeException(
                "Webhook simulation failed with status {$response->status()}: {$response->body()}"
            );
        }

        return $response;
    }

    /**
     * Send webhook using a custom HTTP client (useful for Laravel test cases).
     *
     * @param  callable(string $url, array $payload, array $headers): mixed  $httpClient
     */
    public function sendUsing(callable $httpClient): mixed
    {
        if ($this->url === null) {
            throw new RuntimeException('Webhook URL not set. Use ->to($url) or ->url($url) to set the target URL.');
        }

        $payload = $this->factory->toArray();

        $headers = array_merge([
            'Content-Type' => 'application/json',
            'X-Chip-Event' => $payload['event_type'] ?? 'purchase.paid',
        ], $this->headers);

        return $httpClient($this->url, $payload, $headers);
    }

    /**
     * Dispatch the webhook event directly without HTTP request.
     * Useful for unit testing listeners.
     */
    public function dispatch(): void
    {
        $payload = $this->factory->toArray();
        $eventTypeString = $payload['event_type'] ?? 'purchase.paid';
        $eventType = WebhookEventType::fromString($eventTypeString);

        if ($eventType === null) {
            return;
        }

        // Dispatch generic event
        WebhookReceived::dispatch($eventTypeString, $payload);

        // Dispatch typed event
        $this->dispatchTypedEvent($eventType, $payload);
    }

    /**
     * Create a simulated HTTP request object.
     * Useful for testing middleware and controllers directly.
     *
     * @param  array<string, string>  $headers
     */
    public function toRequest(string $uri = '/chip/webhook', array $headers = []): Request
    {
        $payload = $this->factory->toArray();
        $content = json_encode($payload);

        return Request::create(
            uri: $uri,
            method: 'POST',
            content: $content !== false ? $content : '{}',
            server: $this->formatServerHeaders(array_merge([
                'Content-Type' => 'application/json',
                'X-Chip-Event' => $payload['event_type'] ?? 'purchase.paid',
            ], $this->headers, $headers))
        );
    }

    /**
     * Create a Purchase data object from the payload.
     */
    public function toPurchase(): Purchase
    {
        return Purchase::fromArray($this->factory->toArray());
    }

    /**
     * Create a Webhook data object from the payload.
     */
    public function toWebhook(): Webhook
    {
        return Webhook::fromArray($this->factory->toArray());
    }

    /**
     * Dispatch typed event based on event type.
     *
     * @param  array<string, mixed>  $payload
     */
    private function dispatchTypedEvent(WebhookEventType $eventType, array $payload): void
    {
        // Only create the DTO that's actually needed for the event type
        if ($eventType->isPurchaseEvent() || $eventType->isPaymentEvent()) {
            $purchase = Purchase::fromArray($payload);

            match ($eventType) {
                WebhookEventType::PurchaseCreated => PurchaseCreated::dispatch($purchase, $payload),
                WebhookEventType::PurchasePaid => PurchasePaid::dispatch($purchase, $payload),
                WebhookEventType::PurchasePaymentFailure => PurchasePaymentFailure::dispatch($purchase, $payload),
                WebhookEventType::PurchaseCancelled => PurchaseCancelled::dispatch($purchase, $payload),
                WebhookEventType::PurchasePendingExecute => PurchasePendingExecute::dispatch($purchase, $payload),
                WebhookEventType::PurchasePendingCharge => PurchasePendingCharge::dispatch($purchase, $payload),
                WebhookEventType::PurchasePendingCapture => PurchasePendingCapture::dispatch($purchase, $payload),
                WebhookEventType::PurchasePendingRelease => PurchasePendingRelease::dispatch($purchase, $payload),
                WebhookEventType::PurchasePendingRefund => PurchasePendingRefund::dispatch($purchase, $payload),
                WebhookEventType::PurchasePendingRecurringTokenDelete => PurchasePendingRecurringTokenDelete::dispatch($purchase, $payload),
                WebhookEventType::PurchaseHold => PurchaseHold::dispatch($purchase, $payload),
                WebhookEventType::PurchaseCaptured => PurchaseCaptured::dispatch($purchase, $payload),
                WebhookEventType::PurchaseReleased => PurchaseReleased::dispatch($purchase, $payload),
                WebhookEventType::PurchasePreauthorized => PurchasePreauthorized::dispatch($purchase, $payload),
                WebhookEventType::PurchaseRecurringTokenDeleted => PurchaseRecurringTokenDeleted::dispatch($purchase, $payload),
                WebhookEventType::PurchaseSubscriptionChargeFailure => PurchaseSubscriptionChargeFailure::dispatch($purchase, $payload),
                WebhookEventType::PaymentRefunded => PaymentRefunded::dispatch($purchase, $payload),
                default => null,
            };
        } elseif ($eventType->isBillingEvent()) {
            $billingClient = BillingTemplateClient::fromArray($payload);
            BillingCancelled::dispatch($billingClient, $payload);
        } elseif ($eventType->isPayoutEvent()) {
            $payout = Payout::fromArray($payload);

            match ($eventType) {
                WebhookEventType::PayoutPending => PayoutPending::dispatch($payout, $payload),
                WebhookEventType::PayoutFailed => PayoutFailed::dispatch($payout, $payload),
                WebhookEventType::PayoutSuccess => PayoutSuccess::dispatch($payout, $payload),
                default => null,
            };
        }
    }

    /**
     * Format headers for the request server array.
     *
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function formatServerHeaders(array $headers): array
    {
        $formatted = [];

        foreach ($headers as $key => $value) {
            $formatted['HTTP_'.mb_strtoupper(str_replace('-', '_', $key))] = $value;
        }

        return $formatted;
    }
}
