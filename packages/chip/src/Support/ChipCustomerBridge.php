<?php

declare(strict_types=1);

namespace AIArmada\Chip\Support;

use AIArmada\Chip\Contracts\ChipCustomerDirectoryInterface;
use Illuminate\Database\Eloquent\Model;

final class ChipCustomerBridge
{
    private const CHECKOUT_SESSION_MODEL = 'AIArmada\\Checkout\\Models\\CheckoutSession';

    private const CUSTOMER_MODEL = 'AIArmada\\Customers\\Models\\Customer';

    public function __construct(
        private readonly ChipCustomerDirectoryInterface $customerDirectory,
    ) {}

    public function resolveCheckoutSessionModel(): string
    {
        $configured = config('chip.integrations.customer_bridge.checkout_session_model');

        return is_string($configured) && $configured !== ''
            ? $configured
            : self::CHECKOUT_SESSION_MODEL;
    }

    public function resolveCustomerModel(): string
    {
        $configured = config('chip.integrations.customer_bridge.customer_model');

        return is_string($configured) && $configured !== ''
            ? $configured
            : self::CUSTOMER_MODEL;
    }

    public function findCheckoutSessionByPaymentId(string $paymentId): ?Model
    {
        $sessionModel = $this->resolveCheckoutSessionModel();

        if (! class_exists($sessionModel)) {
            return null;
        }

        /** @var class-string<Model> $sessionModel */
        return $sessionModel::query()
            ->where('payment_id', $paymentId)
            ->whereNotNull('customer_id')
            ->latest('created_at')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function linkCustomer(Model $checkoutSession, array $payload, string $source = 'chip_customer_bridge'): void
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

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = (string) $value;

        return $string !== '' ? $string : null;
    }
}
