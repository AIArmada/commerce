# Enhanced Webhooks

> **Document:** 06 of 10  
> **Package:** `aiarmada/chip`  
> **Status:** Vision

---

## Overview

Evolve the existing webhook system into a **robust, scalable, and intelligent event processing pipeline** with advanced retry strategies, event enrichment, and real-time monitoring.

---

## Webhook Architecture Evolution

```
┌─────────────────────────────────────────────────────────────┐
│                  ENHANCED WEBHOOK PIPELINE                   │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Chip API ──► Webhook Endpoint                              │
│                    │                                         │
│                    ▼                                         │
│            ┌───────────────┐                                 │
│            │   Validator   │ ─── Signature verification      │
│            └───────┬───────┘                                 │
│                    │                                         │
│                    ▼                                         │
│            ┌───────────────┐                                 │
│            │   Enricher    │ ─── Add context, metadata       │
│            └───────┬───────┘                                 │
│                    │                                         │
│                    ▼                                         │
│            ┌───────────────┐                                 │
│            │   Router      │ ─── Route to handlers           │
│            └───────┬───────┘                                 │
│                    │                                         │
│        ┌───────────┼───────────┐                             │
│        ▼           ▼           ▼                             │
│    [Payment]  [Subscription] [Payout]                        │
│    Handlers    Handlers      Handlers                        │
│        │           │           │                             │
│        └───────────┼───────────┘                             │
│                    ▼                                         │
│            ┌───────────────┐                                 │
│            │   Publisher   │ ─── Emit Laravel events         │
│            └───────────────┘                                 │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Enhanced Event Types

### Extended Event Enum

```php
enum ChipWebhookEvent: string
{
    // Existing events (24)
    case PurchaseCreated = 'purchase.created';
    case PurchaseCompleted = 'purchase.completed';
    case PurchaseFailed = 'purchase.failed';
    // ... existing events
    
    // New Subscription Events
    case SubscriptionCreated = 'subscription.created';
    case SubscriptionActivated = 'subscription.activated';
    case SubscriptionRenewed = 'subscription.renewed';
    case SubscriptionPaymentFailed = 'subscription.payment_failed';
    case SubscriptionPaused = 'subscription.paused';
    case SubscriptionResumed = 'subscription.resumed';
    case SubscriptionCanceled = 'subscription.canceled';
    case SubscriptionExpired = 'subscription.expired';
    case SubscriptionTrialEnding = 'subscription.trial_ending';
    
    // New Dispute Events
    case DisputeOpened = 'dispute.opened';
    case DisputeEvidenceRequired = 'dispute.evidence_required';
    case DisputeWon = 'dispute.won';
    case DisputeLost = 'dispute.lost';
    case DisputeClosed = 'dispute.closed';
    
    // New Billing Events
    case InvoiceCreated = 'invoice.created';
    case InvoicePaid = 'invoice.paid';
    case InvoicePaymentFailed = 'invoice.payment_failed';
    case InvoiceVoided = 'invoice.voided';
    
    // Customer Events
    case CustomerCreated = 'customer.created';
    case CustomerUpdated = 'customer.updated';
    case PaymentMethodAdded = 'customer.payment_method_added';
    case PaymentMethodRemoved = 'customer.payment_method_removed';
    
    public function category(): string
    {
        return match (true) {
            str_starts_with($this->value, 'purchase.') => 'purchase',
            str_starts_with($this->value, 'subscription.') => 'subscription',
            str_starts_with($this->value, 'dispute.') => 'dispute',
            str_starts_with($this->value, 'invoice.') => 'invoice',
            str_starts_with($this->value, 'customer.') => 'customer',
            str_starts_with($this->value, 'payout.') => 'payout',
            str_starts_with($this->value, 'recurring.') => 'recurring',
            default => 'other',
        };
    }
    
    public function isHighPriority(): bool
    {
        return in_array($this, [
            self::PurchaseCompleted,
            self::PurchaseFailed,
            self::SubscriptionPaymentFailed,
            self::DisputeOpened,
            self::PayoutFailed,
        ]);
    }
    
    public function requiresImmediateProcessing(): bool
    {
        return in_array($this, [
            self::PurchaseCompleted,
            self::DisputeOpened,
            self::DisputeEvidenceRequired,
        ]);
    }
}
```

---

## Webhook Handler Pipeline

### EnhancedWebhookController

```php
class EnhancedWebhookController extends Controller
{
    public function __construct(
        private WebhookValidator $validator,
        private WebhookEnricher $enricher,
        private WebhookRouter $router,
        private WebhookLogger $logger,
    ) {}
    
    public function handle(Request $request): Response
    {
        $startTime = microtime(true);
        
        // Validate signature
        if (! $this->validator->validate($request)) {
            $this->logger->logInvalidSignature($request);
            return response()->json(['error' => 'Invalid signature'], 401);
        }
        
        $payload = $request->all();
        $eventType = ChipWebhookEvent::tryFrom($payload['event'] ?? '');
        
        if (! $eventType) {
            $this->logger->logUnknownEvent($payload);
            return response()->json(['error' => 'Unknown event type'], 400);
        }
        
        // Create webhook log
        $log = $this->logger->createLog($eventType, $payload, $request);
        
        try {
            // Enrich payload with context
            $enrichedPayload = $this->enricher->enrich($eventType, $payload);
            
            // Route to appropriate handler
            $result = $this->router->route($eventType, $enrichedPayload);
            
            // Mark as processed
            $log->markProcessed(microtime(true) - $startTime);
            
            return response()->json(['success' => true]);
            
        } catch (RetryableException $e) {
            $log->markForRetry($e->getMessage());
            return response()->json(['error' => 'Retry later'], 503);
            
        } catch (Throwable $e) {
            $log->markFailed($e);
            report($e);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }
}
```

---

## Webhook Enricher

### WebhookEnricher

```php
class WebhookEnricher
{
    public function enrich(ChipWebhookEvent $event, array $payload): EnrichedWebhookPayload
    {
        $enriched = new EnrichedWebhookPayload($event, $payload);
        
        // Add purchase context
        if ($purchaseId = $payload['object']['id'] ?? null) {
            $purchase = ChipPurchase::find($purchaseId);
            if ($purchase) {
                $enriched->setPurchase($purchase);
                $enriched->setCustomer($purchase->customer_email);
                
                // Add related entities
                if ($purchase->subscription_id) {
                    $enriched->setSubscription($purchase->subscription);
                }
            }
        }
        
        // Add timing context
        $enriched->setReceivedAt(now());
        $enriched->setEventTimestamp(
            Carbon::parse($payload['created'] ?? now())
        );
        
        // Calculate event lag
        $enriched->setEventLag(
            $enriched->receivedAt->diffInSeconds($enriched->eventTimestamp)
        );
        
        // Add idempotency key
        $enriched->setIdempotencyKey(
            $this->generateIdempotencyKey($event, $payload)
        );
        
        return $enriched;
    }
    
    private function generateIdempotencyKey(ChipWebhookEvent $event, array $payload): string
    {
        return hash('sha256', json_encode([
            'event' => $event->value,
            'object_id' => $payload['object']['id'] ?? null,
            'created' => $payload['created'] ?? null,
        ]));
    }
}
```

---

## Event Router

### WebhookRouter

```php
class WebhookRouter
{
    /**
     * @var array<string, class-string<WebhookHandler>>
     */
    private array $handlers = [];
    
    public function __construct(
        private Container $container,
    ) {
        $this->registerDefaultHandlers();
    }
    
    public function route(ChipWebhookEvent $event, EnrichedWebhookPayload $payload): WebhookResult
    {
        // Check idempotency
        if ($this->isProcessed($payload->idempotencyKey)) {
            return WebhookResult::duplicate();
        }
        
        // Get handlers for this event
        $handlers = $this->getHandlers($event);
        
        if (empty($handlers)) {
            return WebhookResult::noHandler();
        }
        
        $results = [];
        
        foreach ($handlers as $handlerClass) {
            $handler = $this->container->make($handlerClass);
            
            if ($event->isHighPriority() || $event->requiresImmediateProcessing()) {
                // Process synchronously
                $results[] = $handler->handle($event, $payload);
            } else {
                // Queue for async processing
                ProcessWebhookJob::dispatch($handlerClass, $event, $payload);
                $results[] = WebhookResult::queued();
            }
        }
        
        // Mark as processed
        $this->markProcessed($payload->idempotencyKey);
        
        return WebhookResult::fromMultiple($results);
    }
    
    private function registerDefaultHandlers(): void
    {
        // Purchase handlers
        $this->register(ChipWebhookEvent::PurchaseCompleted, PurchaseCompletedHandler::class);
        $this->register(ChipWebhookEvent::PurchaseFailed, PurchaseFailedHandler::class);
        
        // Subscription handlers
        $this->register(ChipWebhookEvent::SubscriptionCreated, SubscriptionCreatedHandler::class);
        $this->register(ChipWebhookEvent::SubscriptionRenewed, SubscriptionRenewedHandler::class);
        $this->register(ChipWebhookEvent::SubscriptionPaymentFailed, SubscriptionPaymentFailedHandler::class);
        $this->register(ChipWebhookEvent::SubscriptionCanceled, SubscriptionCanceledHandler::class);
        
        // Dispute handlers
        $this->register(ChipWebhookEvent::DisputeOpened, DisputeOpenedHandler::class);
        $this->register(ChipWebhookEvent::DisputeEvidenceRequired, DisputeEvidenceHandler::class);
    }
}
```

---

## Webhook Handlers

### Example Handler

```php
abstract class WebhookHandler
{
    abstract public function handle(
        ChipWebhookEvent $event, 
        EnrichedWebhookPayload $payload
    ): WebhookResult;
    
    protected function emit(object $event): void
    {
        Event::dispatch($event);
    }
}

class SubscriptionPaymentFailedHandler extends WebhookHandler
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private NotificationService $notifications,
    ) {}
    
    public function handle(
        ChipWebhookEvent $event, 
        EnrichedWebhookPayload $payload
    ): WebhookResult {
        $subscription = $payload->subscription;
        
        if (! $subscription) {
            return WebhookResult::skipped('No subscription found');
        }
        
        // Increment failure count
        $subscription->incrementPaymentFailures();
        
        // Check if max retries exceeded
        if ($subscription->payment_failures >= config('chip.subscriptions.max_payment_retries', 3)) {
            // Cancel subscription
            $this->subscriptionService->cancel(
                $subscription,
                reason: 'Payment failures exceeded',
            );
            
            $this->notifications->notifySubscriptionCanceled($subscription);
        } else {
            // Notify about failed payment
            $this->notifications->notifyPaymentFailed(
                $subscription,
                attemptsRemaining: config('chip.subscriptions.max_payment_retries', 3) - $subscription->payment_failures,
            );
        }
        
        // Emit event
        $this->emit(new SubscriptionPaymentFailed($subscription, $payload));
        
        return WebhookResult::handled();
    }
}
```

---

## Retry Strategy

### WebhookRetryManager

```php
class WebhookRetryManager
{
    private array $backoffSchedule = [
        1 => 60,        // 1 minute
        2 => 300,       // 5 minutes
        3 => 900,       // 15 minutes
        4 => 3600,      // 1 hour
        5 => 14400,     // 4 hours
        6 => 43200,     // 12 hours
        7 => 86400,     // 24 hours
    ];
    
    public function shouldRetry(ChipWebhookLog $log): bool
    {
        if ($log->status !== 'failed') {
            return false;
        }
        
        if ($log->retry_count >= count($this->backoffSchedule)) {
            return false;
        }
        
        $nextRetryAt = $log->last_retry_at?->addSeconds(
            $this->backoffSchedule[$log->retry_count + 1] ?? end($this->backoffSchedule)
        );
        
        return $nextRetryAt === null || $nextRetryAt->isPast();
    }
    
    public function retry(ChipWebhookLog $log): WebhookResult
    {
        $log->increment('retry_count');
        $log->update(['last_retry_at' => now()]);
        
        try {
            $event = ChipWebhookEvent::from($log->event);
            $payload = $log->payload;
            
            $enrichedPayload = app(WebhookEnricher::class)->enrich($event, $payload);
            $result = app(WebhookRouter::class)->route($event, $enrichedPayload);
            
            if ($result->isSuccess()) {
                $log->markProcessed(0);
            }
            
            return $result;
            
        } catch (Throwable $e) {
            $log->update(['last_error' => $e->getMessage()]);
            return WebhookResult::failed($e->getMessage());
        }
    }
}
```

---

## Webhook Monitoring

### WebhookMonitor

```php
class WebhookMonitor
{
    public function getHealth(): WebhookHealth
    {
        $last24Hours = now()->subDay();
        
        $stats = ChipWebhookLog::query()
            ->where('created_at', '>=', $last24Hours)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "processed" THEN 1 ELSE 0 END) as processed,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
                AVG(processing_time_ms) as avg_processing_time
            ')
            ->first();
        
        return new WebhookHealth(
            total: $stats->total ?? 0,
            processed: $stats->processed ?? 0,
            failed: $stats->failed ?? 0,
            pending: $stats->pending ?? 0,
            successRate: $stats->total > 0 
                ? round($stats->processed / $stats->total * 100, 2) 
                : 100,
            avgProcessingTime: $stats->avg_processing_time ?? 0,
            isHealthy: ($stats->failed ?? 0) / max(1, $stats->total) < 0.05,
        );
    }
    
    public function getEventDistribution(Carbon $since): array
    {
        return ChipWebhookLog::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('event, COUNT(*) as count')
            ->groupBy('event')
            ->orderByDesc('count')
            ->pluck('count', 'event')
            ->toArray();
    }
    
    public function getFailedWebhooks(): Collection
    {
        return ChipWebhookLog::query()
            ->where('status', 'failed')
            ->where('retry_count', '<', 7)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();
    }
}
```

---

## Enhanced Webhook Log Model

```php
/**
 * @property string $id
 * @property string $event
 * @property array $payload
 * @property string $status
 * @property int $retry_count
 * @property Carbon|null $processed_at
 * @property Carbon|null $last_retry_at
 * @property string|null $last_error
 * @property string|null $idempotency_key
 * @property float|null $processing_time_ms
 * @property array|null $metadata
 */
class ChipWebhookLog extends Model
{
    use HasUuids;
    
    protected $casts = [
        'payload' => 'array',
        'metadata' => 'array',
        'processed_at' => 'datetime',
        'last_retry_at' => 'datetime',
        'processing_time_ms' => 'float',
    ];
    
    public function markProcessed(float $processingTime): void
    {
        $this->update([
            'status' => 'processed',
            'processed_at' => now(),
            'processing_time_ms' => $processingTime * 1000,
        ]);
    }
    
    public function markFailed(Throwable $exception): void
    {
        $this->update([
            'status' => 'failed',
            'last_error' => $exception->getMessage(),
        ]);
    }
    
    public function markForRetry(string $reason): void
    {
        $this->update([
            'status' => 'pending',
            'last_error' => $reason,
        ]);
    }
}
```

---

## Database Schema

```php
// Enhanced chip_webhook_logs table
Schema::create('chip_webhook_logs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('event');
    $table->json('payload');
    $table->string('status')->default('pending'); // pending, processing, processed, failed
    $table->unsignedInteger('retry_count')->default(0);
    $table->timestamp('processed_at')->nullable();
    $table->timestamp('last_retry_at')->nullable();
    $table->text('last_error')->nullable();
    $table->string('idempotency_key')->unique()->nullable();
    $table->decimal('processing_time_ms', 10, 3)->nullable();
    $table->json('metadata')->nullable();
    $table->string('ip_address')->nullable();
    $table->timestamps();
    
    $table->index('event');
    $table->index('status');
    $table->index(['status', 'retry_count']);
    $table->index('created_at');
});
```

---

## Scheduled Commands

```php
// Retry failed webhooks
$schedule->command('chip:retry-webhooks')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Clean old processed webhooks (keep 30 days)
$schedule->command('chip:clean-webhooks --days=30')
    ->dailyAt('03:00');

// Alert on webhook failures
$schedule->command('chip:check-webhook-health')
    ->everyFifteenMinutes();
```

---

## Navigation

**Previous:** [05-analytics-insights.md](05-analytics-insights.md)  
**Next:** [07-database-evolution.md](07-database-evolution.md)
