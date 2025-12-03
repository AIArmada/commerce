# Cart Package Vision - Security Framework

> **Document:** 07-security-framework.md  
> **Series:** Cart Package Vision  
> **Focus:** Zero-Trust Security, Fraud Detection, Data Protection

---

## Table of Contents

1. [Zero-Trust Cart Security Model](#1-zero-trust-cart-security-model)
2. [Fraud Detection System](#2-fraud-detection-system)
3. [Data Protection & Privacy](#3-data-protection--privacy)
4. [Audit & Compliance](#4-audit--compliance)

---

## 1. Zero-Trust Cart Security Model

### Vision Statement

Implement **zero-trust architecture** where every cart operation is verified, regardless of network location or previous authentication.

### Security Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│               ZERO-TRUST CART SECURITY MODEL                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                  1. REQUEST ENTRY                        │   │
│  │   • Rate limiting per identifier                         │   │
│  │   • Request signing verification                         │   │
│  │   • TLS certificate pinning (mobile)                     │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                  │
│                              ▼                                  │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │               2. IDENTITY VERIFICATION                   │   │
│  │   • Session validation + device fingerprint              │   │
│  │   • Cart ownership verification                          │   │
│  │   • Token-based access for APIs                          │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                  │
│                              ▼                                  │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              3. OPERATION AUTHORIZATION                  │   │
│  │   • Action-level permissions                             │   │
│  │   • Collaborative role checks                            │   │
│  │   • Owner/admin override rules                           │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                  │
│                              ▼                                  │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              4. DATA VALIDATION                          │   │
│  │   • Input sanitization                                   │   │
│  │   • Business rule enforcement                            │   │
│  │   • Price/quantity bounds                                │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                  │
│                              ▼                                  │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              5. AUDIT LOGGING                            │   │
│  │   • Every operation logged                               │   │
│  │   • Immutable audit trail                                │   │
│  │   • Anomaly detection triggers                           │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Implementation

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security;

use AIArmada\Cart\Domain\Contracts\CartOperationInterface;
use AIArmada\Cart\Security\Contracts\SecurityGateInterface;
use AIArmada\Cart\Security\Exceptions\SecurityViolationException;

/**
 * Zero-trust security gate for cart operations.
 */
final class CartSecurityGate implements SecurityGateInterface
{
    /** @var array<SecurityLayerInterface> */
    private array $layers;
    
    public function __construct(
        private RateLimiter $rateLimiter,
        private IdentityVerifier $identityVerifier,
        private AuthorizationChecker $authChecker,
        private DataValidator $validator,
        private AuditLogger $auditLogger,
    ) {
        $this->layers = [
            new RateLimitingLayer($this->rateLimiter),
            new IdentityLayer($this->identityVerifier),
            new AuthorizationLayer($this->authChecker),
            new ValidationLayer($this->validator),
        ];
    }
    
    /**
     * Verify operation through all security layers.
     * 
     * @throws SecurityViolationException
     */
    public function verify(CartOperationInterface $operation, SecurityContext $context): void
    {
        $violations = [];
        
        foreach ($this->layers as $layer) {
            $result = $layer->check($operation, $context);
            
            if (!$result->passed()) {
                $violations[] = $result;
                
                // Some layers are blocking, others accumulate
                if ($layer->isBlocking()) {
                    $this->logAndThrow($operation, $context, $violations);
                }
            }
        }
        
        if (!empty($violations)) {
            $this->logAndThrow($operation, $context, $violations);
        }
        
        // Log successful operation
        $this->auditLogger->logOperation($operation, $context, AuditResult::Success);
    }
    
    /**
     * @throws SecurityViolationException
     */
    private function logAndThrow(
        CartOperationInterface $operation,
        SecurityContext $context,
        array $violations
    ): never {
        $this->auditLogger->logViolation($operation, $context, $violations);
        
        throw new SecurityViolationException(
            'Cart operation denied',
            $violations,
            $operation,
            $context
        );
    }
}
```

### Rate Limiting

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security\RateLimiting;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Tiered rate limiting for cart operations.
 */
final class CartRateLimiter
{
    /**
     * Rate limits by operation type.
     * 
     * @var array<string, array{perMinute: int, perHour: int}>
     */
    private array $limits = [
        'add_item' => ['perMinute' => 60, 'perHour' => 500],
        'update_item' => ['perMinute' => 120, 'perHour' => 1000],
        'remove_item' => ['perMinute' => 60, 'perHour' => 500],
        'clear_cart' => ['perMinute' => 10, 'perHour' => 50],
        'checkout' => ['perMinute' => 5, 'perHour' => 20],
        'merge_cart' => ['perMinute' => 5, 'perHour' => 30],
    ];
    
    public function check(string $identifier, string $operation): RateLimitResult
    {
        $config = $this->limits[$operation] ?? ['perMinute' => 30, 'perHour' => 200];
        
        // Per-minute check
        $minuteKey = "cart:{$operation}:{$identifier}:minute";
        if (RateLimiter::tooManyAttempts($minuteKey, $config['perMinute'])) {
            return RateLimitResult::exceeded(
                $operation,
                'minute',
                RateLimiter::availableIn($minuteKey)
            );
        }
        
        // Per-hour check
        $hourKey = "cart:{$operation}:{$identifier}:hour";
        if (RateLimiter::tooManyAttempts($hourKey, $config['perHour'])) {
            return RateLimitResult::exceeded(
                $operation,
                'hour',
                RateLimiter::availableIn($hourKey)
            );
        }
        
        // Record attempts
        RateLimiter::hit($minuteKey, 60);
        RateLimiter::hit($hourKey, 3600);
        
        return RateLimitResult::allowed();
    }
    
    /**
     * Apply decay for trusted users.
     */
    public function applyTrustMultiplier(string $identifier, float $multiplier): void
    {
        // Trusted users get higher limits
        // multiplier > 1.0 = more allowed, < 1.0 = more restricted
    }
}
```

### Cart Ownership Verification

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security;

/**
 * Verify cart ownership for zero-trust model.
 */
final class CartOwnershipVerifier
{
    public function __construct(
        private CartRepositoryInterface $repository,
    ) {}
    
    /**
     * Verify the requester has access to the cart.
     */
    public function verify(string $cartId, SecurityContext $context): OwnershipResult
    {
        $cart = $this->repository->getById(CartId::fromString($cartId));
        
        if ($cart === null) {
            return OwnershipResult::notFound();
        }
        
        // Owner match
        if ($this->isOwner($cart, $context)) {
            return OwnershipResult::owner();
        }
        
        // Collaborator check
        if ($cart->isCollaborative() && $this->isCollaborator($cart, $context)) {
            $role = $this->getCollaboratorRole($cart, $context);
            return OwnershipResult::collaborator($role);
        }
        
        // Admin override
        if ($context->hasCapability('cart:admin')) {
            return OwnershipResult::admin();
        }
        
        // Session-based cart (guest)
        if ($this->isSessionOwner($cart, $context)) {
            return OwnershipResult::session();
        }
        
        return OwnershipResult::denied('No access to this cart');
    }
    
    private function isOwner(Cart $cart, SecurityContext $context): bool
    {
        if ($cart->getOwnerId() === null) {
            return false;
        }
        
        return $cart->getOwnerId() === $context->getUserId()
            && $cart->getOwnerType() === $context->getUserType();
    }
    
    private function isSessionOwner(Cart $cart, SecurityContext $context): bool
    {
        return $cart->getIdentifier() === $context->getSessionId()
            && $cart->getOwnerId() === null;
    }
    
    private function isCollaborator(Cart $cart, SecurityContext $context): bool
    {
        $collaborators = $cart->getCollaborators() ?? [];
        
        foreach ($collaborators as $collaborator) {
            if ($collaborator['user_id'] === $context->getUserId()) {
                return true;
            }
        }
        
        return false;
    }
    
    private function getCollaboratorRole(Cart $cart, SecurityContext $context): string
    {
        $collaborators = $cart->getCollaborators() ?? [];
        
        foreach ($collaborators as $collaborator) {
            if ($collaborator['user_id'] === $context->getUserId()) {
                return $collaborator['role'] ?? 'viewer';
            }
        }
        
        return 'viewer';
    }
}
```

---

## 2. Fraud Detection System

### Vision Statement

Implement **AI-powered fraud detection** to identify and prevent malicious cart activities in real-time.

### Fraud Signals

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security\Fraud;

/**
 * Signals that indicate potential fraud.
 */
enum FraudSignal: string
{
    // Velocity signals
    case RAPID_CART_CREATION = 'rapid_cart_creation';           // Many carts in short time
    case RAPID_ITEM_CHANGES = 'rapid_item_changes';             // Unusual edit frequency
    case CART_CYCLING = 'cart_cycling';                         // Create-clear-create pattern
    
    // Value signals
    case UNUSUALLY_HIGH_VALUE = 'unusually_high_value';         // Cart value > threshold
    case BULK_QUANTITY = 'bulk_quantity';                       // Single item > normal qty
    case PRICE_MANIPULATION = 'price_manipulation_attempt';     // Tampered prices detected
    
    // Behavioral signals
    case BOT_BEHAVIOR = 'bot_behavior';                         // Non-human interaction
    case KNOWN_BAD_IP = 'known_bad_ip';                         // IP in threat database
    case PROXY_DETECTED = 'proxy_detected';                     // VPN/proxy usage
    case DEVICE_MISMATCH = 'device_mismatch';                   // Fingerprint changed
    
    // Checkout signals
    case MULTIPLE_FAILED_CHECKOUTS = 'multiple_failed_checkouts';
    case CARD_TESTING_PATTERN = 'card_testing_pattern';         // Small amounts
    case ADDRESS_VELOCITY = 'address_velocity';                 // Many addresses
    
    public function severity(): int
    {
        return match($this) {
            self::PRICE_MANIPULATION => 100,
            self::BOT_BEHAVIOR => 90,
            self::CARD_TESTING_PATTERN => 85,
            self::KNOWN_BAD_IP => 80,
            self::RAPID_CART_CREATION => 70,
            self::CART_CYCLING => 60,
            self::UNUSUALLY_HIGH_VALUE => 50,
            self::BULK_QUANTITY => 40,
            default => 30,
        };
    }
}
```

### Fraud Detection Engine

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security\Fraud;

/**
 * Real-time fraud detection for cart operations.
 */
final class FraudDetectionEngine
{
    public function __construct(
        private FraudSignalCollector $signalCollector,
        private FraudScoringModel $scoringModel,
        private FraudActionHandler $actionHandler,
        private int $blockThreshold = 80,
        private int $reviewThreshold = 50,
    ) {}
    
    /**
     * Analyze cart operation for fraud.
     */
    public function analyze(
        CartOperationInterface $operation,
        SecurityContext $context
    ): FraudAnalysisResult {
        // Collect all signals
        $signals = $this->signalCollector->collect($operation, $context);
        
        if (empty($signals)) {
            return FraudAnalysisResult::clean();
        }
        
        // Score the signals
        $score = $this->scoringModel->calculate($signals, $context);
        
        // Determine action
        if ($score >= $this->blockThreshold) {
            $this->actionHandler->block($operation, $context, $signals, $score);
            return FraudAnalysisResult::blocked($score, $signals);
        }
        
        if ($score >= $this->reviewThreshold) {
            $this->actionHandler->flagForReview($operation, $context, $signals, $score);
            return FraudAnalysisResult::review($score, $signals);
        }
        
        // Log for future ML training
        $this->actionHandler->logForAnalysis($operation, $context, $signals, $score);
        
        return FraudAnalysisResult::allowed($score, $signals);
    }
}

/**
 * Collect fraud signals from various sources.
 */
final class FraudSignalCollector
{
    public function __construct(
        private VelocityAnalyzer $velocity,
        private ValueAnalyzer $value,
        private BehaviorAnalyzer $behavior,
        private DeviceAnalyzer $device,
        private IPIntelligence $ipIntel,
    ) {}
    
    /**
     * @return array<FraudSignal>
     */
    public function collect(
        CartOperationInterface $operation,
        SecurityContext $context
    ): array {
        $signals = [];
        
        // Velocity checks
        $signals = array_merge($signals, $this->velocity->analyze(
            $context->getIdentifier(),
            $operation->getType()
        ));
        
        // Value checks (if cart modification)
        if ($operation instanceof CartModificationOperation) {
            $signals = array_merge($signals, $this->value->analyze(
                $operation->getCart(),
                $operation->getChanges()
            ));
        }
        
        // Behavior checks
        $signals = array_merge($signals, $this->behavior->analyze($context));
        
        // Device fingerprint checks
        $signals = array_merge($signals, $this->device->analyze(
            $context->getDeviceFingerprint(),
            $context->getIdentifier()
        ));
        
        // IP intelligence
        $signals = array_merge($signals, $this->ipIntel->analyze(
            $context->getIpAddress()
        ));
        
        return array_unique($signals);
    }
}
```

### Price Manipulation Detection

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security\Fraud\Detectors;

/**
 * Detect price manipulation attempts.
 */
final class PriceManipulationDetector
{
    /**
     * Verify item price hasn't been tampered.
     */
    public function detect(CartItem $item, BuyableInterface $product): PriceCheckResult
    {
        $expectedPrice = $product->getBuyablePrice();
        $submittedPrice = $item->getPrice();
        
        // Exact match required for security
        if ($expectedPrice !== $submittedPrice) {
            return PriceCheckResult::tampered(
                expected: $expectedPrice,
                submitted: $submittedPrice,
                item: $item
            );
        }
        
        // Check quantity bounds
        $maxQuantity = $product->getMaxQuantity() ?? PHP_INT_MAX;
        $minQuantity = $product->getMinQuantity() ?? 1;
        
        if ($item->getQuantity() > $maxQuantity || $item->getQuantity() < $minQuantity) {
            return PriceCheckResult::quantityViolation(
                quantity: $item->getQuantity(),
                min: $minQuantity,
                max: $maxQuantity
            );
        }
        
        return PriceCheckResult::valid();
    }
    
    /**
     * Verify condition values are authorized.
     */
    public function verifyCondition(
        CartCondition $condition,
        ?AuthorizedCondition $authorized
    ): ConditionCheckResult {
        if ($authorized === null) {
            return ConditionCheckResult::unauthorized($condition);
        }
        
        // Verify condition value matches authorized
        if ($condition->getValue() !== $authorized->getValue()) {
            return ConditionCheckResult::valueTampered(
                expected: $authorized->getValue(),
                submitted: $condition->getValue()
            );
        }
        
        // Verify condition type
        if ($condition->getType() !== $authorized->getType()) {
            return ConditionCheckResult::typeTampered(
                expected: $authorized->getType(),
                submitted: $condition->getType()
            );
        }
        
        return ConditionCheckResult::valid();
    }
}
```

---

## 3. Data Protection & Privacy

### Cart Data Encryption

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security\Encryption;

use Illuminate\Contracts\Encryption\Encrypter;

/**
 * Encrypt sensitive cart data at rest.
 */
final class CartDataEncryptor
{
    /**
     * Fields that require encryption.
     */
    private array $sensitiveFields = [
        'items.*.attributes.custom_text',  // Custom engravings, etc.
        'metadata.gift_message',
        'metadata.shipping_notes',
        'metadata.customer_notes',
    ];
    
    public function __construct(
        private Encrypter $encrypter,
    ) {}
    
    /**
     * Encrypt sensitive fields before storage.
     */
    public function encrypt(array $data): array
    {
        foreach ($this->sensitiveFields as $path) {
            $data = $this->encryptPath($data, $path);
        }
        
        return $data;
    }
    
    /**
     * Decrypt sensitive fields after retrieval.
     */
    public function decrypt(array $data): array
    {
        foreach ($this->sensitiveFields as $path) {
            $data = $this->decryptPath($data, $path);
        }
        
        return $data;
    }
    
    private function encryptPath(array $data, string $path): array
    {
        // Handle wildcards in path (items.*.attributes.custom_text)
        $segments = explode('.', $path);
        return $this->processPath($data, $segments, 'encrypt');
    }
    
    private function decryptPath(array $data, string $path): array
    {
        $segments = explode('.', $path);
        return $this->processPath($data, $segments, 'decrypt');
    }
    
    private function processPath(array $data, array $segments, string $operation): array
    {
        // Recursive path traversal with wildcard support
        // Implementation handles items.*.field patterns
        return $data;
    }
}
```

### PII Handling

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security\Privacy;

/**
 * Handle Personally Identifiable Information in carts.
 */
final class PIIHandler
{
    /**
     * Fields classified as PII.
     */
    private array $piiFields = [
        'metadata.email',
        'metadata.phone',
        'metadata.shipping_address',
        'metadata.billing_address',
        'collaborators.*.email',
    ];
    
    /**
     * Redact PII for logging/analytics.
     */
    public function redactForLogging(array $cartData): array
    {
        $redacted = $cartData;
        
        foreach ($this->piiFields as $path) {
            $redacted = $this->redactPath($redacted, $path);
        }
        
        return $redacted;
    }
    
    /**
     * Hash PII for analytics (allows comparison without exposure).
     */
    public function hashForAnalytics(array $cartData): array
    {
        $hashed = $cartData;
        
        foreach ($this->piiFields as $path) {
            $hashed = $this->hashPath($hashed, $path);
        }
        
        return $hashed;
    }
    
    /**
     * Purge PII after retention period.
     */
    public function purgeExpiredPII(Cart $cart, int $retentionDays): Cart
    {
        $expirationDate = now()->subDays($retentionDays);
        
        if ($cart->getCreatedAt() < $expirationDate) {
            foreach ($this->piiFields as $path) {
                $cart = $this->clearPath($cart, $path);
            }
        }
        
        return $cart;
    }
    
    private function redactPath(array $data, string $path): array
    {
        // Replace value with [REDACTED]
        return $data;
    }
    
    private function hashPath(array $data, string $path): array
    {
        // Replace value with SHA-256 hash
        return $data;
    }
}
```

### Data Retention Policy

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security\Privacy;

/**
 * Automated data retention enforcement.
 */
final class DataRetentionPolicy
{
    public function __construct(
        private CartRepositoryInterface $repository,
        private PIIHandler $piiHandler,
        private AuditLogger $auditLogger,
    ) {}
    
    /**
     * Execute retention policy.
     */
    public function execute(): RetentionResult
    {
        $config = config('cart.data_retention');
        
        $results = [
            'abandoned_deleted' => $this->deleteAbandonedCarts(
                $config['abandoned_days'] ?? 90
            ),
            'completed_anonymized' => $this->anonymizeCompletedCarts(
                $config['completed_days'] ?? 365
            ),
            'pii_purged' => $this->purgePII(
                $config['pii_days'] ?? 180
            ),
            'events_archived' => $this->archiveEvents(
                $config['event_days'] ?? 730
            ),
        ];
        
        $this->auditLogger->logRetentionExecution($results);
        
        return new RetentionResult($results);
    }
    
    private function deleteAbandonedCarts(int $days): int
    {
        return $this->repository->deleteWhere([
            ['updated_at', '<', now()->subDays($days)],
            ['checkout_completed_at', '=', null],
        ]);
    }
    
    private function anonymizeCompletedCarts(int $days): int
    {
        // Keep cart for analytics, remove PII
        return 0;
    }
    
    private function purgePII(int $days): int
    {
        return 0;
    }
    
    private function archiveEvents(int $days): int
    {
        // Move to cold storage (S3, etc.)
        return 0;
    }
}
```

---

## 4. Audit & Compliance

### Comprehensive Audit Trail

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security\Audit;

/**
 * Immutable audit trail for all cart operations.
 */
final class CartAuditLogger
{
    public function __construct(
        private AuditStorageInterface $storage,
        private PIIHandler $piiHandler,
    ) {}
    
    /**
     * Log cart operation for audit.
     */
    public function log(CartAuditEntry $entry): void
    {
        // Redact PII from audit entry
        $safeEntry = $entry->withPayload(
            $this->piiHandler->redactForLogging($entry->getPayload())
        );
        
        // Store immutably
        $this->storage->append($safeEntry);
        
        // Emit for real-time monitoring
        event(new CartAuditEvent($safeEntry));
    }
    
    /**
     * Log security violation.
     */
    public function logViolation(
        CartOperationInterface $operation,
        SecurityContext $context,
        array $violations
    ): void {
        $entry = new CartAuditEntry(
            type: AuditEntryType::SecurityViolation,
            cartId: $operation->getCartId(),
            operation: $operation->getType(),
            actor: $context->getActorInfo(),
            payload: [
                'violations' => array_map(
                    fn($v) => $v->toArray(),
                    $violations
                ),
                'ip_address' => $context->getIpAddress(),
                'user_agent' => $context->getUserAgent(),
                'request_id' => $context->getRequestId(),
            ],
            severity: AuditSeverity::High,
            timestamp: now(),
        );
        
        $this->log($entry);
        
        // Alert security team for high severity
        if ($entry->getSeverity() === AuditSeverity::Critical) {
            $this->alertSecurityTeam($entry);
        }
    }
    
    /**
     * Generate compliance report.
     */
    public function generateComplianceReport(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        string $standard = 'PCI-DSS'
    ): ComplianceReport {
        $entries = $this->storage->query([
            'from' => $from,
            'to' => $to,
        ]);
        
        return match($standard) {
            'PCI-DSS' => $this->buildPCIDSSReport($entries),
            'GDPR' => $this->buildGDPRReport($entries),
            'SOC2' => $this->buildSOC2Report($entries),
            default => throw new \InvalidArgumentException("Unknown standard: {$standard}"),
        };
    }
}
```

### Audit Entry Structure

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security\Audit;

final readonly class CartAuditEntry
{
    public function __construct(
        public AuditEntryType $type,
        public ?string $cartId,
        public string $operation,
        public ActorInfo $actor,
        public array $payload,
        public AuditSeverity $severity,
        public \DateTimeImmutable $timestamp,
        public ?string $correlationId = null,
        public ?string $causationId = null,
    ) {}
    
    public function toArray(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'type' => $this->type->value,
            'cart_id' => $this->cartId,
            'operation' => $this->operation,
            'actor' => $this->actor->toArray(),
            'payload' => $this->payload,
            'severity' => $this->severity->value,
            'timestamp' => $this->timestamp->format('c'),
            'correlation_id' => $this->correlationId,
            'causation_id' => $this->causationId,
        ];
    }
}

enum AuditEntryType: string
{
    case CartCreated = 'cart.created';
    case CartModified = 'cart.modified';
    case CartDeleted = 'cart.deleted';
    case CartAccessed = 'cart.accessed';
    case CartMerged = 'cart.merged';
    case CheckoutStarted = 'checkout.started';
    case CheckoutCompleted = 'checkout.completed';
    case CheckoutFailed = 'checkout.failed';
    case SecurityViolation = 'security.violation';
    case FraudDetected = 'fraud.detected';
    case PIIPurged = 'pii.purged';
    case DataExported = 'data.exported';
}

enum AuditSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
```

---

## Summary: Security Framework Priorities

| Component | Implementation | Complexity | Priority |
|-----------|---------------|------------|----------|
| Rate Limiting | Immediate | Low | **P0** |
| Audit Logging | Immediate | Low | **P0** |
| Ownership Verification | Immediate | Medium | **P0** |
| Input Validation | Immediate | Low | **P0** |
| Price Manipulation Detection | Phase 1 | Medium | **P1** |
| Fraud Signal Collection | Phase 1 | Medium | **P1** |
| Data Encryption | Phase 2 | Medium | **P2** |
| ML Fraud Scoring | Phase 3 | High | **P3** |
| Compliance Reports | Phase 3 | Medium | **P3** |

---

**Next:** [08-ecosystem-integration.md](08-ecosystem-integration.md) - Cross-Package Events, Commerce Pipeline
