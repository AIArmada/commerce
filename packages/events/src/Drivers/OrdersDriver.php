<?php

declare(strict_types=1);

namespace AIArmada\Events\Drivers;

use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Contracts\ProviderAwarePaymentProcessorInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Contracts\EventOrderDriver;
use AIArmada\Events\Data\EventOrderData;
use AIArmada\Events\Data\EventOrderItemData;
use AIArmada\Events\Data\EventRefundMatchData;
use AIArmada\Events\Data\EventRefundResultData;
use AIArmada\Events\Data\EventRefundStateData;
use AIArmada\Orders\Contracts\OrderServiceInterface;
use AIArmada\Orders\Enums\RefundStatus;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\Models\OrderRefund;
use AIArmada\Ticketing\Models\TicketType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class OrdersDriver implements EventOrderDriver
{
    public function __construct(
        private readonly ?OrderServiceInterface $orders,
        private readonly ?PaymentGatewayResolverInterface $gateways,
    ) {}

    public function isPaidOrder(string $orderId): bool
    {
        return $this->order($orderId)->isPaid();
    }

    public function orderNumber(string $orderId): ?string
    {
        $number = Order::query()->whereKey($orderId)->value('order_number');

        return is_string($number) && $number !== '' ? $number : null;
    }

    public function orderSnapshot(string $orderId): ?EventOrderData
    {
        /** @var Order|null $order */
        $order = Order::query()
            ->with(['items.purchasable', 'customer'])
            ->find($orderId);

        if (! $order instanceof Order) {
            return null;
        }

        $customer = $order->getRelationValue('customer');
        $customer = $customer instanceof Model ? $customer : null;

        $items = [];

        foreach ($order->getRelation('items') as $orderItem) {
            $items[] = $this->orderItemData($orderItem, $order);
        }

        return new EventOrderData(
            id: (string) $order->getKey(),
            orderClass: Order::class,
            customerId: $customer?->getKey() !== null ? (string) $customer->getKey() : null,
            customerName: $customer === null ? null : $this->customerName($customer),
            customerEmail: $customer === null ? null : $this->customerContact($customer, 'resolveEmail'),
            customerPhone: $customer === null ? null : $this->customerContact($customer, 'resolvePhone'),
            customerType: $customer?->getMorphClass(),
            items: $items,
        );
    }

    public function refundState(string $refundId): ?EventRefundStateData
    {
        /** @var OrderRefund|null $refund */
        $refund = OrderRefund::query()->find($refundId);

        if (! $refund instanceof OrderRefund) {
            return null;
        }

        return new EventRefundStateData(
            id: (string) $refund->getKey(),
            status: $refund->status->value,
            providerSubmissionStarted: $refund->hasProviderSubmissionStarted(),
            amount: (int) $refund->amount,
            reason: is_string($refund->reason) ? $refund->reason : null,
        );
    }

    public function orderItemSnapshot(string $orderItemId): ?EventOrderItemData
    {
        /** @var OrderItem|null $orderItem */
        $orderItem = OrderItem::query()->with('order')->find($orderItemId);

        if (! $orderItem instanceof OrderItem) {
            return null;
        }

        $order = $orderItem->getRelationValue('order');

        if (! $order instanceof Order) {
            return null;
        }

        $orderItem->loadMissing('purchasable');

        return $this->orderItemData($orderItem, $order);
    }

    public function assertOrderVisible(string $orderId): void
    {
        if (method_exists(Order::class, 'ownerScopeConfig') && ! Order::ownerScopeConfig()->enabled) {
            Order::query()->findOrFail($orderId);

            return;
        }

        OwnerWriteGuard::findOrFailForOwner(Order::class, $orderId);
    }

    public function markItemFulfillment(string $orderItemId): ?array
    {
        /** @var OrderItem|null $orderItem */
        $orderItem = OrderItem::query()->find($orderItemId);

        if (! $orderItem instanceof OrderItem) {
            return null;
        }

        $originalOptions = $orderItem->getAttribute('options');
        $options = is_array($originalOptions) ? $originalOptions : [];

        if (($options['event_fulfillment'] ?? null) === 'event_registration') {
            return null;
        }

        $orderItem->forceFill([
            'options' => [...$options, 'event_fulfillment' => 'event_registration'],
        ])->save();

        return ['options' => $originalOptions];
    }

    public function restoreItemOptions(string $orderItemId, ?array $options): void
    {
        /** @var OrderItem|null $orderItem */
        $orderItem = OrderItem::query()->find($orderItemId);

        if (! $orderItem instanceof OrderItem) {
            return;
        }

        $orderItem->forceFill(['options' => $options])->save();
    }

    public function createRefund(
        string $orderId,
        int $amount,
        string $reason,
        ?string $transactionId = null,
        array $metadata = [],
    ): string {
        $order = $this->order($orderId);

        if (! $order->isPaid()) {
            throw new InvalidArgumentException('This order has no completed payment to refund.');
        }

        $payment = $this->completedPayment($order);
        $this->assertOnlinePayment($payment);
        $metadata['payment_id'] = $this->paymentId($payment);
        $metadata['provider'] = $this->paymentProvider($payment);

        $refunds = OwnerContext::withOwner($this->ownerOf($order), fn (): array => $this->orderService()->createPendingRefund(
            order: $order,
            amount: $amount,
            transactionId: $transactionId ?? 'event-refund-' . $orderId,
            reason: $reason,
            metadata: $metadata,
            payment: $payment,
        ));

        if (count($refunds) !== 1) {
            throw new RuntimeException('The selected payment did not produce exactly one refund reservation.');
        }

        return (string) $refunds[0]->getKey();
    }

    public function refundIdForRegistration(string $orderId, string $registrationId): ?string
    {
        $refund = $this->order($orderId)->refunds->first(
            static fn (OrderRefund $refund): bool => (string) data_get($refund->metadata ?? [], 'event_registration_id') === $registrationId,
        );

        return $refund instanceof OrderRefund ? (string) $refund->getKey() : null;
    }

    public function claimRefund(string $refundId): bool
    {
        $refund = $this->refund($refundId);

        return OwnerContext::withOwner(
            $this->ownerOf($refund),
            fn (): bool => $this->orderService()->claimPendingRefundSubmission($refund),
        );
    }

    public function completeRefund(string $refundId, ?string $transactionId = null): void
    {
        $refund = $this->refund($refundId);

        OwnerContext::withOwner($this->ownerOf($refund), function () use ($refund, $transactionId): void {
            $this->orderService()->completePendingRefund($refund, $transactionId);
        });
    }

    public function failRefund(string $refundId, string $reason): void
    {
        $reason = mb_trim($reason);
        $refund = $this->refund($refundId);

        OwnerContext::withOwner($this->ownerOf($refund), function () use ($refund, $reason): void {
            $this->orderService()->failPendingRefund(
                $refund,
                $reason !== '' ? mb_substr($reason, 0, 500) : 'Refund failed.',
            );
        });
    }

    public function matchProviderRefundConfirmation(
        string $provider,
        ?string $providerRefundId,
        ?string $relatedPaymentId,
        int $amount,
        string $currency,
        ?string $eventRegistrationId,
    ): EventRefundMatchData {
        $provider = mb_strtolower(mb_trim($provider));
        $paymentId = $providerRefundId !== null ? mb_trim($providerRefundId) : null;

        if ($paymentId !== null && $paymentId !== '') {
            $refunds = $this->providerRefundQuery($amount, $currency, includeCompleted: true, provider: $provider)
                ->where('metadata->provider_refund_id', $paymentId);

            if ($eventRegistrationId !== null && $eventRegistrationId !== '') {
                $refunds->where('metadata->event_registration_id', $eventRegistrationId);
            }

            $refunds = $refunds->get();

            if ($refunds->count() === 1) {
                $refund = $refunds->first();

                return $this->matchedRefund($refund);
            }

            if ($refunds->count() > 1) {
                return new EventRefundMatchData(refundId: null, matchedCount: $refunds->count(), pending: false, ownerType: null, ownerId: null);
            }
        }

        if ($eventRegistrationId !== null && $eventRegistrationId !== '') {
            $refunds = $this->providerRefundQuery($amount, $currency, includeCompleted: true, provider: $provider)
                ->where('metadata->event_registration_id', $eventRegistrationId);

            if ($relatedPaymentId !== null && $relatedPaymentId !== '') {
                $refunds->where('metadata->payment_id', $relatedPaymentId);
            }

            $refunds = $refunds->get();

            if ($refunds->count() === 1) {
                $refund = $refunds->first();

                return $this->matchedRefund($refund);
            }

            if ($refunds->count() > 1) {
                return new EventRefundMatchData(refundId: null, matchedCount: $refunds->count(), pending: false, ownerType: null, ownerId: null);
            }
        }

        if ($relatedPaymentId !== null && $relatedPaymentId !== '') {
            $refunds = $this->providerRefundQuery($amount, $currency, includeCompleted: false, provider: $provider)
                ->where('metadata->payment_id', $relatedPaymentId);

            if ($eventRegistrationId !== null && $eventRegistrationId !== '') {
                $refunds->where('metadata->event_registration_id', $eventRegistrationId);
            }

            $refunds = $refunds->get();

            if ($refunds->count() === 1) {
                return $this->matchedRefund($refunds->first());
            }

            return new EventRefundMatchData(refundId: null, matchedCount: $refunds->count(), pending: false, ownerType: null, ownerId: null);
        }

        return new EventRefundMatchData(refundId: null, matchedCount: 0, pending: false, ownerType: null, ownerId: null);
    }

    public function correlateRefundProviderReference(
        string $refundId,
        ?string $providerRefundId,
        ?string $relatedPaymentId,
    ): bool {
        if (in_array($providerRefundId, [null, '', $relatedPaymentId], true)) {
            return true;
        }

        return DB::transaction(function () use ($refundId, $providerRefundId): bool {
            $refund = $this->refund($refundId, lock: true);
            $metadata = is_array($refund->metadata) ? $refund->metadata : [];
            $existingId = data_get($metadata, 'provider_refund_id');

            if (is_scalar($existingId) && mb_trim((string) $existingId) !== '') {
                return mb_trim((string) $existingId) === $providerRefundId;
            }

            $metadata['provider_refund_id'] = $providerRefundId;

            $refund->forceFill(['metadata' => $metadata])->save();

            return true;
        });
    }

    public function submitRefund(string $refundId): EventRefundResultData
    {
        $refund = $this->refund($refundId);
        $order = $refund->order;

        if (! $order instanceof Order) {
            throw new RuntimeException('The refund is not attached to an order.');
        }

        $payment = $this->completedPayment($order);
        $this->assertOnlinePayment($payment);

        $processor = $this->resolveProcessor($payment);
        $provider = $this->paymentProvider($payment);
        $paymentId = $this->paymentId($payment);
        $amount = (int) $refund->amount;
        $reason = (string) ($refund->reason ?? '');

        $result = $provider !== null && $processor instanceof ProviderAwarePaymentProcessorInterface
            ? $processor->refundForProvider($provider, $paymentId, $amount, $reason)
            : $processor->refund($paymentId, $amount, $reason);

        return new EventRefundResultData(
            status: $result->status->value,
            transactionId: $result->transactionId,
            paymentId: $result->paymentId,
            message: $result->message,
        );
    }

    private function order(string $orderId): Order
    {
        return Order::query()->with(['payments'])->findOrFail($orderId);
    }

    private function refund(string $refundId, bool $lock = false): OrderRefund
    {
        return OwnerScopeOverride::withoutIncludeGlobal(
            static fn (): OrderRefund => OrderRefund::query()
                ->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())
                ->findOrFail($refundId),
        );
    }

    private function matchedRefund(OrderRefund $refund): EventRefundMatchData
    {
        $config = OrderRefund::ownerScopeConfig();

        return new EventRefundMatchData(
            refundId: (string) $refund->getKey(),
            matchedCount: 1,
            pending: $refund->isPending(),
            ownerType: $refund->getAttribute($config->ownerTypeColumn),
            ownerId: $refund->getAttribute($config->ownerIdColumn),
        );
    }

    private function ownerOf(Order | OrderRefund $model): ?Model
    {
        $config = $model::ownerScopeConfig();

        return OwnerContext::fromTypeAndId(
            $model->getAttribute($config->ownerTypeColumn),
            $model->getAttribute($config->ownerIdColumn),
        );
    }

    private function orderService(): OrderServiceInterface
    {
        return $this->orders ?? throw new RuntimeException('The AIArmada orders integration is unavailable.');
    }

    private function completedPayment(Order $order): OrderPayment
    {
        $payment = $order->payments->first(
            static fn (OrderPayment $candidate): bool => $candidate->isCompleted(),
        );

        if (! $payment instanceof OrderPayment) {
            throw new InvalidArgumentException('This order has no completed payment to refund.');
        }

        return $payment;
    }

    private function assertOnlinePayment(OrderPayment $payment): void
    {
        if (str_starts_with(mb_strtolower(mb_trim((string) $payment->gateway)), 'offline_')) {
            throw new InvalidArgumentException('Offline admissions must be refunded outside the online payment workflow.');
        }
    }

    private function paymentId(OrderPayment $payment): string
    {
        $paymentId = data_get($payment->metadata ?? [], 'payment_id');

        if (! is_scalar($paymentId) || mb_trim((string) $paymentId) === '') {
            throw new InvalidArgumentException('The completed payment has no provider reference for refunding.');
        }

        return mb_trim((string) $paymentId);
    }

    private function paymentProvider(OrderPayment $payment): ?string
    {
        $provider = data_get($payment->metadata ?? [], 'provider');

        return is_string($provider) && mb_trim($provider) !== '' ? mb_trim($provider) : null;
    }

    private function resolveProcessor(OrderPayment $payment): PaymentProcessorInterface
    {
        $identifier = mb_trim((string) $payment->gateway);
        if ($identifier !== '' && $this->gateways?->hasGateway($identifier)) {
            return $this->gateways->resolve($identifier);
        }

        throw new RuntimeException(sprintf('The payment processor [%s] is no longer available.', $identifier));
    }

    private function orderItemData(OrderItem $orderItem, Order $order): EventOrderItemData
    {
        $purchasable = $orderItem->getRelationValue('purchasable');
        $options = $orderItem->getAttribute('options');

        return new EventOrderItemData(
            id: (string) $orderItem->getKey(),
            orderId: (string) $order->getKey(),
            orderClass: Order::class,
            orderItemClass: $orderItem::class,
            ticketTypeId: $purchasable instanceof TicketType ? (string) $purchasable->getKey() : null,
            quantity: (int) $orderItem->getAttribute('quantity'),
            unitPrice: (int) $orderItem->getAttribute('unit_price'),
            total: (int) $orderItem->getAttribute('total'),
            discountAmount: (int) $orderItem->getAttribute('discount_amount'),
            taxAmount: (int) $orderItem->getAttribute('tax_amount'),
            currency: is_string($orderItem->getAttribute('currency')) ? $orderItem->getAttribute('currency') : null,
            options: is_array($options) ? $options : [],
        );
    }

    private function customerName(Model $customer): ?string
    {
        foreach (['full_name', 'name'] as $attribute) {
            if (! $customer->hasAttribute($attribute)
                && ! $customer->hasGetMutator($attribute)
                && ! $customer->hasAttributeGetMutator($attribute)) {
                continue;
            }

            $name = $this->customerText($customer, $attribute);

            if ($name !== null) {
                return $name;
            }
        }

        return null;
    }

    private function customerText(Model $customer, string $attribute): ?string
    {
        $value = $customer->getAttribute($attribute);

        if (! is_scalar($value)) {
            return null;
        }

        $text = mb_trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function customerContact(Model $customer, string $method): ?string
    {
        if (! method_exists($customer, $method)) {
            return null;
        }

        $value = call_user_func([$customer, $method]);

        if (! is_scalar($value)) {
            return null;
        }

        $text = mb_trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * @return Builder<OrderRefund>
     */
    private function providerRefundQuery(
        int $amount,
        string $currency,
        bool $includeCompleted,
        ?string $provider = null,
    ): Builder {
        $statuses = $includeCompleted
            ? [RefundStatus::Pending, RefundStatus::Completed]
            : [RefundStatus::Pending];

        $query = OrderRefund::query()
            ->withoutOwnerScope()
            ->whereIn('status', $statuses)
            ->where('amount', $amount)
            ->where('currency', mb_strtoupper($currency))
            ->whereNotNull('metadata->event_registration_id');

        if ($provider !== null && $provider !== '') {
            $query->where('metadata->provider', $provider);
        }

        return $query;
    }
}
