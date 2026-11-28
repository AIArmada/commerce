<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

use AIArmada\Orders\Data\OrderIntakeData;

interface OrderServiceInterface
{
    /**
     * @param array<string, mixed> $orderData
     * @param array<array<string, mixed>> $items
     * @param array<string, mixed>|null $billingAddress
     * @param array<string, mixed>|null $shippingAddress
     */
    public function createOrder(
        array $orderData,
        array $items,
        ?array $billingAddress = null,
        ?array $shippingAddress = null,
        ?string $intakeSource = null,
        ?string $intakeId = null,
    ): OrderIntakeData;

    /**
     * @param array<string, mixed>|null $billingAddress
     * @param array<string, mixed>|null $shippingAddress
     */
    public function createFromCart(
        string $cartId,
        string $customerType,
        string $customerId,
        ?array $billingAddress = null,
        ?array $shippingAddress = null,
        ?string $intakeSource = null,
        ?string $intakeId = null,
        ?string $sessionId = null,
    ): OrderIntakeData;

    public function find(string $orderId): ?OrderIntakeData;
}
