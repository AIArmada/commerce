<?php

declare(strict_types=1);

namespace AIArmada\Chip\Services\Collect;

use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\DataObjects\ClientDetails;
use AIArmada\Chip\DataObjects\Product;
use AIArmada\Chip\DataObjects\Purchase;
use AIArmada\Chip\Exceptions\ChipValidationException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class PurchasesApi extends CollectApi
{
    protected ?CacheRepository $cache;

    public function __construct(
        ?CacheRepository $cache,
        ChipCollectClient $client,
    ) {
        $this->cache = $cache;
        parent::__construct($client);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Purchase
    {
        $data['brand_id'] = $data['brand_id'] ?? $this->client->getBrandId();

        $this->validatePurchaseData($data);

        $response = $this->attempt(
            fn () => $this->client->post('purchases/', $data),
            'Failed to create CHIP purchase',
            ['data' => $data]
        );

        return Purchase::fromArray($response);
    }

    public function find(string $purchaseId): Purchase
    {
        $response = $this->attempt(
            fn () => $this->client->get("purchases/{$purchaseId}/"),
            'Failed to retrieve CHIP purchase',
            ['purchase_id' => $purchaseId]
        );

        return Purchase::fromArray($response);
    }

    public function cancel(string $purchaseId): Purchase
    {
        $response = $this->attempt(
            fn () => $this->client->post("purchases/{$purchaseId}/cancel/"),
            'Failed to cancel CHIP purchase',
            ['purchase_id' => $purchaseId]
        );

        return Purchase::fromArray($response);
    }

    public function refund(string $purchaseId, ?int $amount = null): Purchase
    {
        $payload = [];
        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        $response = $this->attempt(
            fn () => $this->client->post("purchases/{$purchaseId}/refund/", $payload),
            'Failed to refund CHIP purchase',
            ['purchase_id' => $purchaseId, 'amount' => $amount]
        );

        return Purchase::fromArray($response);
    }

    public function charge(string $purchaseId, string $recurringToken): Purchase
    {
        $response = $this->attempt(
            fn () => $this->client->post("purchases/{$purchaseId}/charge/", [
                'recurring_token' => $recurringToken,
            ]),
            'Failed to charge CHIP purchase',
            ['purchase_id' => $purchaseId, 'recurring_token' => $recurringToken]
        );

        return Purchase::fromArray($response);
    }

    public function capture(string $purchaseId, ?int $amount = null): Purchase
    {
        $payload = [];
        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        $response = $this->attempt(
            fn () => $this->client->post("purchases/{$purchaseId}/capture/", $payload),
            'Failed to capture CHIP purchase',
            ['purchase_id' => $purchaseId, 'amount' => $amount]
        );

        return Purchase::fromArray($response);
    }

    public function release(string $purchaseId): Purchase
    {
        $response = $this->attempt(
            fn () => $this->client->post("purchases/{$purchaseId}/release/"),
            'Failed to release CHIP purchase',
            ['purchase_id' => $purchaseId]
        );

        return Purchase::fromArray($response);
    }

    public function markAsPaid(string $purchaseId, ?int $paidOn = null): Purchase
    {
        $payload = [];
        if ($paidOn !== null) {
            $payload['paid_on'] = $paidOn;
        }

        $response = $this->attempt(
            fn () => $this->client->post("purchases/{$purchaseId}/mark_as_paid/", $payload),
            'Failed to mark CHIP purchase as paid',
            ['purchase_id' => $purchaseId, 'paid_on' => $paidOn]
        );

        return Purchase::fromArray($response);
    }

    public function resendInvoice(string $purchaseId): Purchase
    {
        $response = $this->attempt(
            fn () => $this->client->post("purchases/{$purchaseId}/resend_invoice/"),
            'Failed to resend CHIP purchase invoice',
            ['purchase_id' => $purchaseId]
        );

        return Purchase::fromArray($response);
    }

    public function deleteRecurringToken(string $purchaseId): void
    {
        $this->attempt(
            fn () => $this->client->delete("purchases/{$purchaseId}/recurring_token/"),
            'Failed to delete CHIP recurring token',
            ['purchase_id' => $purchaseId]
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function paymentMethods(array $filters = []): array
    {
        if ($this->cache === null) {
            return $this->fetchPaymentMethods($filters);
        }

        $cacheKey = config('chip.cache.prefix', 'chip:').'payment_methods:'.md5((string) json_encode($filters));
        $ttl = config('chip.cache.ttl.payment_methods')
            ?? config('chip.cache.default_ttl', 3600);

        return $this->cache->remember($cacheKey, $ttl, fn () => $this->fetchPaymentMethods($filters));
    }

    /**
     * @param  array<int, Product>  $products
     * @param  array<string, mixed>  $options
     */
    public function createCheckoutPurchase(array $products, ClientDetails $client, array $options = []): Purchase
    {
        $data = [
            'client' => $client->toArray(),
            'purchase' => [
                'products' => array_map(
                    fn (Product $product) => $product->toArray(),
                    $products
                ),
                'currency' => $options['currency'] ?? config('chip.defaults.currency', 'MYR'),
            ],
            'brand_id' => $this->client->getBrandId(),
            'send_receipt' => $options['send_receipt'] ?? config('chip.defaults.send_receipt', false),
            'creator_agent' => config('chip.defaults.creator_agent', 'Laravel Package'),
            'platform' => config('chip.defaults.platform', 'api'),
        ];

        if (! empty($options['purchase_overrides']) && is_array($options['purchase_overrides'])) {
            $data['purchase'] = array_merge(
                $data['purchase'],
                array_filter(
                    $options['purchase_overrides'],
                    static fn ($value) => $value !== null
                )
            );
        }

        // Only add reference if it's not null/empty
        if (! empty($options['reference'])) {
            $data['reference'] = $options['reference'];
        }

        if (! empty($options['payment_method_whitelist'])) {
            $data['payment_method_whitelist'] = is_string($options['payment_method_whitelist'])
                ? array_filter(array_map('trim', explode(',', $options['payment_method_whitelist'])))
                : $options['payment_method_whitelist'];
        } else {
            $defaultWhitelist = array_filter(array_map('trim', explode(',', (string) config('chip.defaults.payment_method_whitelist', ''))));

            if ($defaultWhitelist !== []) {
                $data['payment_method_whitelist'] = $defaultWhitelist;
            }
        }

        foreach ([
            'success_redirect' => $options['success_redirect'] ?? config('chip.defaults.success_redirect'),
            'failure_redirect' => $options['failure_redirect'] ?? config('chip.defaults.failure_redirect'),
            'cancel_redirect' => $options['cancel_redirect'] ?? null,
            'success_callback' => $options['success_callback'] ?? null,
        ] as $key => $value) {
            if (! empty($value)) {
                $data[$key] = $value;
            }
        }

        return $this->create($data);
    }

    public function publicKey(): string
    {
        $key = $this->attempt(
            fn () => $this->client->get('public_key/'),
            'Failed to get CHIP public key'
        );

        return is_string($key) ? $key : (string) ($key['public_key'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function fetchPaymentMethods(array $filters): array
    {
        $queryString = http_build_query($filters);
        $endpoint = 'payment_methods/'.($queryString ? '?'.$queryString : '');

        return $this->attempt(
            fn () => $this->client->get($endpoint),
            'Failed to get CHIP payment methods',
            ['filters' => $filters]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function validatePurchaseData(array $data): void
    {
        if (! isset($data['purchase']) || ! is_array($data['purchase'])) {
            throw new ChipValidationException('Purchase payload is required');
        }

        if (empty($data['brand_id'])) {
            throw new ChipValidationException('brand_id is required');
        }

        $hasClientPayload = isset($data['client']) && is_array($data['client']);
        $hasClientId = isset($data['client_id']) && ! empty($data['client_id']);

        if (! $hasClientPayload && ! $hasClientId) {
            throw new ChipValidationException('Either client or client_id must be provided');
        }

        if ($hasClientPayload && empty($data['client']['email'])) {
            throw new ChipValidationException('client.email is required when client payload is provided');
        }

        if (! isset($data['purchase']['products']) || empty($data['purchase']['products'])) {
            throw new ChipValidationException('Purchase must have at least one product');
        }

        foreach ($data['purchase']['products'] as $product) {
            if (! isset($product['name']) || ! isset($product['price'])) {
                throw new ChipValidationException('Each product must have name and price');
            }
        }
    }
}
