<?php

declare(strict_types=1);

namespace AIArmada\Chip\Testing;

use AIArmada\Chip\Actions\DispatchChipWebhookAction;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Data\WebhookData;
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
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RuntimeException;

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

    public static function forEvent(WebhookEventType $eventType): self
    {
        return (new self)->factory(
            WebhookFactory::fromPayload(WebhookFactory::forEvent($eventType))
        );
    }

    public static function paid(): self
    {
        return (new self)->factory(WebhookFactory::make()->paid());
    }

    public static function created(): self
    {
        return (new self)->factory(WebhookFactory::make()->created());
    }

    public static function refunded(): self
    {
        return (new self)->factory(WebhookFactory::make()->refunded());
    }

    public static function cancelled(): self
    {
        return (new self)->factory(WebhookFactory::make()->cancelled());
    }

    public static function expired(): self
    {
        return (new self)->factory(WebhookFactory::make()->expired());
    }

    public static function failed(): self
    {
        return (new self)->factory(WebhookFactory::make()->failed());
    }

    /**
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
            PurchasePendingExecute::class,
            PurchasePendingCharge::class,
            PurchasePendingCapture::class,
            PurchasePendingRelease::class,
            PurchasePendingRefund::class,
            PurchasePendingRecurringTokenDelete::class,
            PurchasePreauthorized::class,
            PurchaseHold::class,
            PurchaseCaptured::class,
            PurchaseReleased::class,
            PurchaseRecurringTokenDeleted::class,
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
     * @param  class-string  $eventClass
     */
    public static function assertDispatched(string $eventClass, ?callable $callback = null): void
    {
        Event::assertDispatched($eventClass, $callback);
    }

    /**
     * @param  class-string  $eventClass
     */
    public static function assertNotDispatched(string $eventClass): void
    {
        Event::assertNotDispatched($eventClass);
    }

    public static function withoutSignatureVerification(): void
    {
        config(['chip.webhooks.verify_signature' => false]);
    }

    public function factory(WebhookFactory $factory): self
    {
        $this->factory = $factory;

        return $this;
    }

    public function to(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function url(string $url): self
    {
        return $this->to($url);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function amount(int $amountInCents): self
    {
        $this->factory->amount($amountInCents);

        return $this;
    }

    public function reference(string $reference): self
    {
        $this->factory->reference($reference);

        return $this;
    }

    public function purchaseId(string $id): self
    {
        $this->factory->purchaseId($id);

        return $this;
    }

    public function clientId(string $id): self
    {
        $this->factory->clientId($id);

        return $this;
    }

    public function customer(string $email, string $name, string $phone = '+60123456789'): self
    {
        $this->factory->customer($email, $name, $phone);

        return $this;
    }

    public function addProduct(string $name, int $priceInCents, string $quantity = '1.0000', string $category = 'product'): self
    {
        $this->factory->addProduct($name, $priceInCents, $quantity, $category);

        return $this;
    }

    public function paymentMethod(string $method): self
    {
        $this->factory->paymentMethod($method);

        return $this;
    }

    public function fpx(): self
    {
        $this->factory->fpx();

        return $this;
    }

    public function card(): self
    {
        $this->factory->card();

        return $this;
    }

    public function isTest(bool $isTest = true): self
    {
        $this->factory->isTest($isTest);

        return $this;
    }

    public function live(): self
    {
        $this->factory->live();

        return $this;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function with(array $overrides): self
    {
        $this->factory->with($overrides);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->factory->toArray();
    }

    public function getPayloadJson(): string
    {
        return $this->factory->toJson();
    }

    /**
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
     * Uses the unified DispatchChipWebhookAction seam.
     */
    public function dispatch(): void
    {
        $payload = $this->enrichPayloadForRuntime($this->factory->toArray());
        $eventTypeString = $payload['event_type'] ?? 'purchase.paid';

        /** @var DispatchChipWebhookAction $dispatchAction */
        $dispatchAction = app(DispatchChipWebhookAction::class);

        $dispatchAction->execute($eventTypeString, $payload);
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function toRequest(string $uri = '/chip/webhooks', array $headers = []): Request
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

    public function toPurchase(): PurchaseData
    {
        return PurchaseData::from($this->factory->toArray());
    }

    public function toWebhook(): WebhookData
    {
        return WebhookData::from($this->factory->toArray());
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function formatServerHeaders(array $headers): array
    {
        $formatted = [];

        foreach ($headers as $key => $value) {
            $normalizedKey = mb_strtoupper(str_replace('-', '_', $key));

            if (in_array($normalizedKey, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $formatted[$normalizedKey] = $value;

                continue;
            }

            $formatted['HTTP_' . $normalizedKey] = $value;
        }

        return $formatted;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function enrichPayloadForRuntime(array $payload): array
    {
        if (! (bool) config('chip.owner.enabled', false)) {
            return $payload;
        }

        if (isset($payload['__owner_type'], $payload['__owner_id'])) {
            return $payload;
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return $payload;
        }

        $payload['__owner_type'] = $owner->getMorphClass();
        $payload['__owner_id'] = (string) $owner->getKey();

        return $payload;
    }
}
