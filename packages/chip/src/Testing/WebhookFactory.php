<?php

declare(strict_types=1);

namespace AIArmada\Chip\Testing;

use AIArmada\Chip\Enums\WebhookEventType;
use Illuminate\Support\Str;

/**
 * Factory for creating CHIP webhook payloads for testing.
 *
 * Generates realistic webhook payloads that match the actual CHIP API structure.
 */
final class WebhookFactory
{
    /** @var array<string, mixed> */
    private array $overrides = [];

    private string $eventType = 'purchase.paid';

    private string $status = 'paid';

    private int $amount = 10000;

    private string $currency = 'MYR';

    private string $paymentMethod = 'fpx';

    /** @var array<array{name: string, price: int, quantity: string, category: string}> */
    private array $products = [];

    private ?string $reference = null;

    private ?string $purchaseId = null;

    private ?string $clientId = null;

    private ?string $brandId = null;

    private ?string $companyId = null;

    private string $customerEmail = 'test@example.com';

    private string $customerName = 'Test Customer';

    private string $customerPhone = '+60123456789';

    private bool $isTest = true;

    public static function make(): self
    {
        return new self;
    }

    // Static factory methods for common events

    /**
     * Create a purchase.paid payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function purchasePaid(array $overrides = []): array
    {
        return self::make()->paid()->with($overrides)->toArray();
    }

    /**
     * Create a purchase.created payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function purchaseCreated(array $overrides = []): array
    {
        return self::make()->created()->with($overrides)->toArray();
    }

    /**
     * Create a purchase.payment_failure payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function purchasePaymentFailure(array $overrides = []): array
    {
        return self::make()->failed()->eventType('purchase.payment_failure')->with($overrides)->toArray();
    }

    /**
     * Create a purchase.cancelled payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function purchaseCancelled(array $overrides = []): array
    {
        return self::make()->cancelled()->with($overrides)->toArray();
    }

    /**
     * Create a purchase.preauthorized payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function purchasePreauthorized(array $overrides = []): array
    {
        return self::make()
            ->eventType('purchase.preauthorized')
            ->status('preauthorized')
            ->with(['recurring_token' => $overrides['recurring_token'] ?? Str::uuid()->toString()])
            ->with($overrides)
            ->toArray();
    }

    /**
     * Create a purchase.hold payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function purchaseHold(array $overrides = []): array
    {
        return self::make()->eventType('purchase.hold')->status('hold')->with($overrides)->toArray();
    }

    /**
     * Create a purchase.captured payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function purchaseCaptured(array $overrides = []): array
    {
        return self::make()->eventType('purchase.captured')->status('captured')->with($overrides)->toArray();
    }

    /**
     * Create a purchase.released payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function purchaseReleased(array $overrides = []): array
    {
        return self::make()->eventType('purchase.released')->status('released')->with($overrides)->toArray();
    }

    /**
     * Create a purchase.subscription_charge_failure payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function purchaseSubscriptionChargeFailure(array $overrides = []): array
    {
        return self::make()
            ->eventType('purchase.subscription_charge_failure')
            ->status('error')
            ->with(['purchase' => ['metadata' => ['subscription_type' => $overrides['subscription_type'] ?? 'default']]])
            ->with($overrides)
            ->toArray();
    }

    /**
     * Create a payment.refunded payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function paymentRefunded(array $overrides = []): array
    {
        return self::make()->refunded()->eventType('payment.refunded')->with($overrides)->toArray();
    }

    /**
     * Create a billing_template_client.subscription_billing_cancelled payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function billingCancelled(array $overrides = []): array
    {
        return array_merge([
            'id' => $overrides['id'] ?? Str::uuid()->toString(),
            'type' => 'billing_template_client',
            'event_type' => 'billing_template_client.subscription_billing_cancelled',
            'status' => 'cancelled',
            'billing_template_id' => $overrides['billing_template_id'] ?? Str::uuid()->toString(),
            'client_id' => $overrides['client_id'] ?? Str::uuid()->toString(),
            'recurring_token' => $overrides['recurring_token'] ?? Str::uuid()->toString(),
            'is_test' => $overrides['is_test'] ?? true,
            'created_on' => time(),
            'updated_on' => time(),
        ], $overrides);
    }

    /**
     * Create a payout.pending payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function payoutPending(array $overrides = []): array
    {
        return self::payoutPayload('payout.pending', 'pending', $overrides);
    }

    /**
     * Create a payout.success payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function payoutSuccess(array $overrides = []): array
    {
        return self::payoutPayload('payout.success', 'success', $overrides);
    }

    /**
     * Create a payout.failed payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function payoutFailed(array $overrides = []): array
    {
        return self::payoutPayload('payout.failed', 'failed', array_merge([
            'error_code' => 'insufficient_funds',
            'error_message' => 'Insufficient funds for payout',
        ], $overrides));
    }

    /**
     * Create a payout payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function payoutPayload(string $eventType, string $status, array $overrides = []): array
    {
        return array_merge([
            'id' => $overrides['id'] ?? Str::uuid()->toString(),
            'type' => 'payout',
            'event_type' => $eventType,
            'status' => $status,
            'amount' => $overrides['amount'] ?? 10000,
            'currency' => $overrides['currency'] ?? 'MYR',
            'recipient_name' => $overrides['recipient_name'] ?? 'Test Recipient',
            'recipient_account' => $overrides['recipient_account'] ?? '1234567890',
            'recipient_bank' => $overrides['recipient_bank'] ?? 'Maybank',
            'reference' => $overrides['reference'] ?? 'PAYOUT-'.Str::random(8),
            'is_test' => $overrides['is_test'] ?? true,
            'created_on' => time(),
            'updated_on' => time(),
        ], $overrides);
    }

    /**
     * Create a payload for any webhook event type.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function forEvent(WebhookEventType $eventType, array $overrides = []): array
    {
        return match ($eventType) {
            WebhookEventType::PurchaseCreated => self::purchaseCreated($overrides),
            WebhookEventType::PurchasePaid => self::purchasePaid($overrides),
            WebhookEventType::PurchasePaymentFailure => self::purchasePaymentFailure($overrides),
            WebhookEventType::PurchaseCancelled => self::purchaseCancelled($overrides),
            WebhookEventType::PurchasePreauthorized => self::purchasePreauthorized($overrides),
            WebhookEventType::PurchaseHold => self::purchaseHold($overrides),
            WebhookEventType::PurchaseCaptured => self::purchaseCaptured($overrides),
            WebhookEventType::PurchaseReleased => self::purchaseReleased($overrides),
            WebhookEventType::PurchaseSubscriptionChargeFailure => self::purchaseSubscriptionChargeFailure($overrides),
            WebhookEventType::PaymentRefunded => self::paymentRefunded($overrides),
            WebhookEventType::BillingTemplateClientSubscriptionBillingCancelled => self::billingCancelled($overrides),
            WebhookEventType::PayoutPending => self::payoutPending($overrides),
            WebhookEventType::PayoutSuccess => self::payoutSuccess($overrides),
            WebhookEventType::PayoutFailed => self::payoutFailed($overrides),
            default => self::make()->eventType($eventType->value)->with($overrides)->toArray(),
        };
    }

    // Fluent builder methods

    public function paid(): self
    {
        $this->eventType = 'purchase.paid';
        $this->status = 'paid';

        return $this;
    }

    public function created(): self
    {
        $this->eventType = 'purchase.created';
        $this->status = 'created';

        return $this;
    }

    public function cancelled(): self
    {
        $this->eventType = 'purchase.cancelled';
        $this->status = 'cancelled';

        return $this;
    }

    public function refunded(): self
    {
        $this->eventType = 'purchase.refunded';
        $this->status = 'refunded';

        return $this;
    }

    public function expired(): self
    {
        $this->eventType = 'purchase.expired';
        $this->status = 'expired';

        return $this;
    }

    public function failed(): self
    {
        $this->eventType = 'purchase.error';
        $this->status = 'error';

        return $this;
    }

    public function eventType(string $eventType): self
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function status(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function amount(int $amountInCents): self
    {
        $this->amount = $amountInCents;

        return $this;
    }

    public function currency(string $currency): self
    {
        $this->currency = strtoupper($currency);

        return $this;
    }

    public function paymentMethod(string $method): self
    {
        $this->paymentMethod = $method;

        return $this;
    }

    public function fpx(): self
    {
        return $this->paymentMethod('fpx');
    }

    public function card(): self
    {
        return $this->paymentMethod('card');
    }

    public function ewallet(string $wallet = 'touch_n_go'): self
    {
        return $this->paymentMethod($wallet);
    }

    /**
     * @param  array<array{name: string, price: int, quantity?: string, category?: string}>  $products
     */
    public function products(array $products): self
    {
        $this->products = array_map(fn (array $product) => [
            'name' => $product['name'],
            'price' => $product['price'],
            'quantity' => $product['quantity'] ?? '1.0000',
            'category' => $product['category'] ?? 'product',
            'discount' => 0,
            'tax_percent' => '0.00',
            'total_price_override' => null,
        ], $products);

        return $this;
    }

    public function addProduct(string $name, int $priceInCents, string $quantity = '1.0000', string $category = 'product'): self
    {
        $this->products[] = [
            'name' => $name,
            'price' => $priceInCents,
            'quantity' => $quantity,
            'category' => $category,
            'discount' => 0,
            'tax_percent' => '0.00',
            'total_price_override' => null,
        ];

        return $this;
    }

    public function reference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function purchaseId(string $id): self
    {
        $this->purchaseId = $id;

        return $this;
    }

    public function clientId(string $id): self
    {
        $this->clientId = $id;

        return $this;
    }

    public function brandId(string $id): self
    {
        $this->brandId = $id;

        return $this;
    }

    public function companyId(string $id): self
    {
        $this->companyId = $id;

        return $this;
    }

    public function customer(string $email, string $name, string $phone = '+60123456789'): self
    {
        $this->customerEmail = $email;
        $this->customerName = $name;
        $this->customerPhone = $phone;

        return $this;
    }

    public function isTest(bool $isTest = true): self
    {
        $this->isTest = $isTest;

        return $this;
    }

    public function live(): self
    {
        return $this->isTest(false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function with(array $overrides): self
    {
        $this->overrides = array_replace_recursive($this->overrides, $overrides);

        return $this;
    }

    /**
     * Generate the webhook payload array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $now = time();
        $purchaseId = $this->purchaseId ?? (string) Str::uuid();
        $clientId = $this->clientId ?? (string) Str::uuid();
        $brandId = $this->brandId ?? (string) Str::uuid();
        $companyId = $this->companyId ?? (string) Str::uuid();
        $reference = $this->reference ?? (string) Str::uuid();

        $products = $this->products;
        if (empty($products)) {
            $products = [
                [
                    'name' => 'Test Product',
                    'price' => $this->amount,
                    'category' => 'product',
                    'discount' => 0,
                    'quantity' => '1.0000',
                    'tax_percent' => '0.00',
                    'total_price_override' => null,
                ],
            ];
        }

        $total = array_sum(array_map(
            fn (array $p) => (int) $p['price'] * (int) (float) $p['quantity'],
            $products
        ));

        $statusHistory = $this->buildStatusHistory($now);

        $payload = [
            'id' => $purchaseId,
            'due' => $now + 3600,
            'type' => 'purchase',
            'client' => [
                'cc' => [],
                'bcc' => [],
                'city' => '',
                'email' => $this->customerEmail,
                'phone' => $this->customerPhone,
                'state' => '',
                'country' => 'MY',
                'zip_code' => '',
                'bank_code' => '',
                'full_name' => $this->customerName,
                'brand_name' => '',
                'legal_name' => '',
                'tax_number' => '',
                'client_type' => null,
                'bank_account' => '',
                'personal_code' => '',
                'shipping_city' => '',
                'shipping_state' => '',
                'street_address' => '',
                'delivery_methods' => [
                    ['method' => 'email', 'options' => []],
                ],
                'shipping_country' => '',
                'shipping_zip_code' => '',
                'registration_number' => '',
                'shipping_street_address' => '',
            ],
            'issued' => date('Y-m-d', $now),
            'status' => $this->status,
            'is_test' => $this->isTest,
            'payment' => $this->status === 'paid' ? [
                'amount' => $total,
                'paid_on' => $now,
                'currency' => $this->currency,
                'fee_amount' => (int) round($total * 0.01),
                'net_amount' => $total - (int) round($total * 0.01),
                'description' => '',
                'is_outgoing' => false,
                'payment_type' => 'purchase',
                'pending_amount' => 0,
                'remote_paid_on' => $now,
                'owned_bank_code' => null,
                'owned_bank_account' => null,
                'pending_unfreeze_on' => null,
                'owned_bank_account_id' => null,
            ] : null,
            'product' => 'purchases',
            'user_id' => null,
            'brand_id' => $brandId,
            'order_id' => null,
            'platform' => 'api',
            'purchase' => [
                'debt' => 0,
                'notes' => '',
                'total' => $total,
                'currency' => $this->currency,
                'language' => 'en',
                'metadata' => [],
                'products' => $products,
                'timezone' => 'UTC',
                'due_strict' => false,
                'email_message' => '',
                'total_override' => null,
                'shipping_options' => [],
                'subtotal_override' => null,
                'total_tax_override' => null,
                'has_upsell_products' => false,
                'payment_method_details' => [],
                'request_client_details' => [],
                'total_discount_override' => null,
            ],
            'client_id' => $clientId,
            'reference' => $reference,
            'viewed_on' => $this->status !== 'created' ? $now : null,
            'company_id' => $companyId,
            'created_on' => $now,
            'event_type' => $this->eventType,
            'updated_on' => $now,
            'invoice_url' => null,
            'can_retrieve' => false,
            'checkout_url' => "https://gate.chip-in.asia/p/{$purchaseId}/invoice/",
            'send_receipt' => false,
            'skip_capture' => false,
            'creator_agent' => 'AIArmada/Chip',
            'referral_code' => null,
            'can_chargeback' => false,
            'issuer_details' => [
                'website' => 'https://example.com',
                'brand_name' => 'Test Brand',
                'legal_city' => 'Kuala Lumpur',
                'legal_name' => 'Test Company Sdn. Bhd.',
                'tax_number' => '',
                'bank_accounts' => [
                    ['bank_code' => 'MBBEMYKL', 'bank_account' => '123456789012'],
                ],
                'legal_country' => 'MY',
                'legal_zip_code' => '50000',
                'registration_number' => '123456-A',
                'legal_street_address' => '123 Test Street',
            ],
            'marked_as_paid' => false,
            'status_history' => $statusHistory,
            'cancel_redirect' => "https://example.com/checkout/cancelled/{$reference}",
            'created_from_ip' => '127.0.0.1',
            'direct_post_url' => null,
            'force_recurring' => false,
            'recurring_token' => null,
            'failure_redirect' => "https://example.com/checkout/failed/{$reference}",
            'success_callback' => 'https://example.com/api/webhooks/chip',
            'success_redirect' => "https://example.com/checkout/success/{$reference}",
            'transaction_data' => [
                'flow' => 'payform',
                'extra' => [],
                'country' => 'MY',
                'attempts' => $this->status === 'paid' ? [
                    [
                        'flow' => 'payform',
                        'type' => 'execute',
                        'error' => null,
                        'extra' => [],
                        'country' => 'MY',
                        'client_ip' => '127.0.0.1',
                        'fee_amount' => (int) round($total * 0.01),
                        'successful' => true,
                        'payment_method' => $this->paymentMethod,
                        'processing_time' => $now,
                        'processing_tx_id' => $purchaseId,
                    ],
                ] : [],
                'payment_method' => $this->paymentMethod,
                'processing_tx_id' => $purchaseId,
            ],
            'upsell_campaigns' => [],
            'refundable_amount' => $this->status === 'paid' ? $total : 0,
            'is_recurring_token' => false,
            'billing_template_id' => null,
            'currency_conversion' => null,
            'reference_generated' => 'TEST' . random_int(100, 999),
            'refund_availability' => 'all',
            'referral_campaign_id' => null,
            'retain_level_details' => null,
            'referral_code_details' => null,
            'referral_code_generated' => null,
            'payment_method_whitelist' => null,
        ];

        return array_replace_recursive($payload, $this->overrides);
    }

    /**
     * Generate the webhook payload as JSON string.
     */
    public function toJson(): string
    {
        $json = json_encode($this->toArray(), JSON_PRETTY_PRINT);

        return $json !== false ? $json : '{}';
    }

    /**
     * Build status history based on current status.
     *
     * @return array<array{status: string, timestamp: int}>
     */
    private function buildStatusHistory(int $now): array
    {
        $history = [
            ['status' => 'created', 'timestamp' => $now - 10],
        ];

        if ($this->status !== 'created') {
            $history[] = ['status' => 'viewed', 'timestamp' => $now - 5];
        }

        if (in_array($this->status, ['paid', 'refunded', 'cancelled', 'expired', 'error'])) {
            $history[] = ['status' => $this->status, 'timestamp' => $now];
        }

        return $history;
    }
}
