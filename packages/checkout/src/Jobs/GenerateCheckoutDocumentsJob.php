<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Jobs;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Docs\Contracts\DocumentServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class GenerateCheckoutDocumentsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string>  $documentTypes
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly string $orderId,
        public readonly array $documentTypes,
    ) {}

    public function handle(): void
    {
        if (! interface_exists(DocumentServiceInterface::class)) {
            return;
        }

        $documentService = app(DocumentServiceInterface::class);

        $session = CheckoutSession::find($this->sessionId);

        if ($session === null) {
            return;
        }

        foreach ($this->documentTypes as $type) {
            $this->generateDocument($documentService, $session, $type);
        }
    }

    private function generateDocument(mixed $documentService, CheckoutSession $session, string $type): void
    {
        $data = $this->buildDocumentData($session, $type);

        match ($type) {
            'invoice' => $documentService->generateInvoice($this->orderId, $data),
            'receipt' => $documentService->generateReceipt($this->orderId, $data),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDocumentData(CheckoutSession $session, string $type): array
    {
        $cartSnapshot = $session->cart_snapshot ?? [];
        $billingData = $session->billing_data ?? [];
        $shippingData = $session->shipping_data ?? [];

        return [
            'type' => $type,
            'order_id' => $this->orderId,
            'checkout_session_id' => $this->sessionId,
            'customer_id' => $session->customer_id,
            'items' => $cartSnapshot['items'] ?? [],
            'billing' => $billingData,
            'shipping' => $shippingData,
            'subtotal' => $session->subtotal,
            'discount_total' => $session->discount_total,
            'shipping_total' => $session->shipping_total,
            'tax_total' => $session->tax_total,
            'grand_total' => $session->grand_total,
            'currency' => $session->currency,
            'payment_method' => $session->selected_payment_gateway,
            'payment_id' => $session->payment_id,
            'issued_at' => now()->toIso8601String(),
        ];
    }
}
