<?php

declare(strict_types=1);

namespace AIArmada\Chip\Actions;

use AIArmada\Chip\Contracts\ChipCustomerDirectoryInterface;
use Illuminate\Database\Eloquent\Model;

final class LinkChipCustomerFromCheckout
{
    private const CHECKOUT_SESSION_MODEL = 'AIArmada\\Checkout\\Models\\CheckoutSession';

    private const CUSTOMER_MODEL = 'AIArmada\\Customers\\Models\\Customer';

    public function __construct(
        private readonly ChipCustomerDirectoryInterface $customerDirectory,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $purchaseId, array $payload, string $source = 'chip_customer_bridge'): void
    {
        $checkoutSessionModel = $this->resolveCheckoutSessionModel();

        if (! class_exists($checkoutSessionModel)) {
            return;
        }

        /** @var class-string<Model> $checkoutSessionModel */
        /** @var Model|null $checkoutSession */
        $checkoutSession = $checkoutSessionModel::query()
            ->where('payment_id', $purchaseId)
            ->whereNotNull('customer_id')
            ->latest('created_at')
            ->first();

        if ($checkoutSession === null) {
            return;
        }

        $this->handleForCheckoutSession($checkoutSession, $payload, $source);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleForCheckoutSession(Model $checkoutSession, array $payload, string $source = 'chip_customer_bridge'): void
    {
        $customerModel = $this->resolveCustomerModel();

        if (! class_exists($customerModel)) {
            return;
        }

        $chipCustomerId = $this->stringValue($payload['client_id'] ?? null);

        if ($chipCustomerId === null) {
            return;
        }

        $customerId = $this->stringValue($checkoutSession->getAttribute('customer_id'));

        if ($customerId === null) {
            return;
        }

        /** @var class-string<Model> $customerModel */
        /** @var Model|null $customer */
        $customer = $customerModel::query()->find($customerId);

        if ($customer === null) {
            return;
        }

        $purchaseId = $this->stringValue($checkoutSession->getAttribute('payment_id'));

        $this->customerDirectory->link($customer, $chipCustomerId, array_filter([
            'source' => $source,
            'checkout_session_id' => (string) $checkoutSession->getKey(),
            'chip_purchase_id' => $purchaseId,
        ], static fn (mixed $value): bool => $value !== null));
    }

    private function resolveCheckoutSessionModel(): string
    {
        $configured = config('chip.integrations.customer_bridge.checkout_session_model');

        return is_string($configured) && $configured !== ''
            ? $configured
            : self::CHECKOUT_SESSION_MODEL;
    }

    private function resolveCustomerModel(): string
    {
        $configured = config('chip.integrations.customer_bridge.customer_model');

        return is_string($configured) && $configured !== ''
            ? $configured
            : self::CUSTOMER_MODEL;
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = (string) $value;

        return $string !== '' ? $string : null;
    }
}
