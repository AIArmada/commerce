<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Jobs;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Data\OwnerJobContext;
use AIArmada\CommerceSupport\Traits\OwnerContextJob;
use AIArmada\Orders\Contracts\OrderDocuments;
use AIArmada\Orders\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class GenerateCheckoutDocumentsJob implements OwnerScopedJob, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use OwnerContextJob;
    use Queueable;

    /**
     * @param  array<string>  $documentTypes
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly string $orderId,
        public readonly array $documentTypes,
        public readonly ?string $ownerType = null,
        public readonly string | int | null $ownerId = null,
        public readonly bool $ownerIsGlobal = false,
    ) {}

    public function ownerContext(): OwnerJobContext
    {
        return new OwnerJobContext(
            ownerType: $this->ownerType,
            ownerId: $this->ownerId,
            ownerIsGlobal: $this->ownerIsGlobal,
        );
    }

    protected function performJob(): void
    {
        $session = CheckoutSession::query()->forOwner(includeGlobal: false)->find($this->sessionId);

        if ($session === null) {
            return;
        }

        $order = $this->resolveOrder($session);

        if (! $order instanceof Order || ! $order->isFullyPaid()) {
            return;
        }

        $transactionId = $this->resolveTransactionId($session);
        $gateway = $this->resolveGateway($session);

        foreach ($this->documentTypes as $type) {
            if (in_array($type, ['invoice', 'receipt'], true)) {
                app(OrderDocuments::class)->issue((string) $order->getKey(), $type, $transactionId, $gateway);
            }
        }
    }

    private function resolveOrder(CheckoutSession $session): ?Order
    {
        $order = $session->order()
            ->whereKey($this->orderId)
            ->first();

        if (! $order instanceof Order) {
            return null;
        }

        return $order;
    }

    private function resolveTransactionId(CheckoutSession $session): string
    {
        $paymentData = $session->payment_data ?? [];

        $transactionId = $paymentData['transaction_id']
            ?? $paymentData['payment_id']
            ?? $session->payment_id
            ?? 'unknown';

        return (string) $transactionId;
    }

    private function resolveGateway(CheckoutSession $session): string
    {
        $paymentData = $session->payment_data ?? [];

        $gateway = $paymentData['gateway']
            ?? $session->selected_payment_gateway
            ?? 'unknown';

        return (string) $gateway;
    }
}
