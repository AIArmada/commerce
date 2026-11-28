<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Lifecycle\Actions;

use AIArmada\Cart\Runtime\CartManagerInterface;
use AIArmada\Orders\Contracts\OrderServiceInterface;
use AIArmada\Orders\Data\OrderIntakeData;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Support\AssertsOrderOwnerBoundary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

final readonly class OrderIntake implements OrderServiceInterface
{
    use AssertsOrderOwnerBoundary;

    public function __construct(
        private CreateOrder $orders,
        private CreateOrderFromCart $cartOrders,
    ) {}

    public function createOrder(
        array $orderData,
        array $items,
        ?array $billingAddress = null,
        ?array $shippingAddress = null,
        ?string $intakeSource = null,
        ?string $intakeId = null,
    ): OrderIntakeData {
        return $this->snapshot($this->orders->execute($orderData, $items, $billingAddress, $shippingAddress, $intakeSource, $intakeId));
    }

    public function createFromCart(
        string $cartId,
        string $customerType,
        string $customerId,
        ?array $billingAddress = null,
        ?array $shippingAddress = null,
        ?string $intakeSource = null,
        ?string $intakeId = null,
        ?string $sessionId = null,
    ): OrderIntakeData {
        $cart = app(CartManagerInterface::class)->getById($cartId);
        $customerClass = Relation::getMorphedModel($customerType) ?? $customerType;

        if ($cart === null || ! is_a($customerClass, Model::class, true)) {
            throw new InvalidArgumentException('The checkout cart or customer is unavailable.');
        }

        $customer = $customerClass::query()->findOrFail($customerId);

        return $this->snapshot($this->cartOrders->execute($cart, $customer, $billingAddress, $shippingAddress, $intakeSource, $intakeId, $sessionId));
    }

    public function find(string $orderId): ?OrderIntakeData
    {
        $order = Order::query()->forOwner()->find($orderId);

        if ($order === null) {
            return null;
        }

        $this->assertOwnerBoundaryForMutation($order, __METHOD__);

        return $this->snapshot($order);
    }

    private function snapshot(Order $order): OrderIntakeData
    {
        return new OrderIntakeData(
            id: (string) $order->getKey(),
            orderNumber: $order->order_number,
            grandTotal: (int) $order->grand_total,
            currency: $order->currency,
            paidTotal: $order->getTotalPaid(),
        );
    }
}
