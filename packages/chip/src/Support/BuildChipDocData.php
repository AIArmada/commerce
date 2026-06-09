<?php

declare(strict_types=1);

namespace AIArmada\Chip\Support;

use AIArmada\Chip\Events\PaymentRefunded;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Models\Purchase;
use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\States\DocStatus;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\Refunded;
use Illuminate\Support\Arr;

final class BuildChipDocData
{
    public function forPayment(Purchase $purchase, PurchasePaid $event, DocType $docType): DocData
    {
        $amount = $event->getAmount();
        $currency = $event->getCurrency();

        $customerData = $this->extractCustomerData(
            $purchase,
            fn () => $event->getCustomerName(),
            fn () => $event->getCustomerEmail(),
        );

        $items = $this->extractItems($purchase, $amount);

        return new DocData(
            docType: $docType->value,
            status: DocStatus::fromString(Paid::class),
            issueDate: now(),
            dueDate: null,
            subtotal: $amount / 100,
            taxAmount: 0,
            discountAmount: 0,
            total: $amount / 100,
            currency: $currency,
            customerData: $customerData,
            items: $items,
            notes: $this->generatePaymentNotes($event),
            docableType: $purchase->getMorphClass(),
            docableId: (string) $purchase->id,
            generatePdf: (bool) config('chip.integrations.docs.generate_pdf', false),
            metadata: [
                'chip_purchase_id' => $event->getPurchaseId(),
                'chip_reference' => $event->getReference(),
                'payment_method' => $event->getPaymentMethod(),
                'is_test' => $event->isTest(),
            ],
        );
    }

    public function forRefund(Purchase $purchase, PaymentRefunded $event, DocType $docType, ?Doc $originalInvoice): DocData
    {
        $amount = $event->getAmount();
        $currency = $event->getCurrency();

        $customerData = array_filter([
            'name' => Arr::get($purchase->client, 'full_name')
                ?? Arr::get($purchase->client, 'legal_name')
                ?? $event->payment?->getClientName(),
            'email' => Arr::get($purchase->client, 'email') ?? $event->payment?->getClientEmail(),
            'phone' => Arr::get($purchase->client, 'phone'),
            'address' => Arr::get($purchase->client, 'street_address'),
            'city' => Arr::get($purchase->client, 'city'),
            'state' => Arr::get($purchase->client, 'state'),
            'postcode' => Arr::get($purchase->client, 'zip_code'),
            'country' => Arr::get($purchase->client, 'country'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $metadata = [
            'chip_purchase_id' => $event->getPurchaseId(),
            'chip_payment_id' => $event->payment?->getPaymentId(),
            'chip_reference' => $event->getReference(),
            'is_test' => $event->isTest(),
            'refund' => true,
        ];

        if ($originalInvoice !== null) {
            $metadata['original_invoice_id'] = $originalInvoice->id;
            $metadata['original_invoice_number'] = $originalInvoice->doc_number;
        }

        $notes = $this->generateRefundNotes($event, $originalInvoice);

        $description = 'Refund';

        if ($originalInvoice !== null) {
            $description .= ' for Invoice #' . $originalInvoice->doc_number;
        }

        return new DocData(
            docType: $docType->value,
            status: DocStatus::fromString(Refunded::class),
            issueDate: now(),
            dueDate: null,
            subtotal: $amount / 100,
            taxAmount: 0,
            discountAmount: 0,
            total: $amount / 100,
            currency: $currency,
            customerData: $customerData,
            items: [
                [
                    'description' => $description,
                    'quantity' => 1,
                    'price' => $amount / 100,
                    'total' => $amount / 100,
                ],
            ],
            notes: $notes,
            docableType: $purchase->getMorphClass(),
            docableId: (string) $purchase->id,
            generatePdf: (bool) config('chip.integrations.docs.generate_pdf', false),
            metadata: $metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function extractCustomerData(Purchase $purchase, callable $getName, callable $getEmail): array
    {
        return array_filter([
            'name' => Arr::get($purchase->client, 'full_name')
                ?? Arr::get($purchase->client, 'legal_name')
                ?? $getName(),
            'email' => Arr::get($purchase->client, 'email') ?? $getEmail(),
            'phone' => Arr::get($purchase->client, 'phone'),
            'address' => Arr::get($purchase->client, 'street_address'),
            'city' => Arr::get($purchase->client, 'city'),
            'state' => Arr::get($purchase->client, 'state'),
            'postcode' => Arr::get($purchase->client, 'zip_code'),
            'country' => Arr::get($purchase->client, 'country'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractItems(Purchase $purchase, int $amount): array
    {
        $purchaseData = $purchase->purchase ?? [];
        $products = Arr::get($purchaseData, 'products', []);

        if (empty($products)) {
            return [
                [
                    'name' => Arr::get($purchaseData, 'description', 'Payment'),
                    'description' => Arr::get($purchaseData, 'description', 'Payment'),
                    'quantity' => 1,
                    'price' => $amount / 100,
                    'total' => $amount / 100,
                ],
            ];
        }

        $items = [];

        foreach ($products as $product) {
            $items[] = [
                'description' => Arr::get($product, 'name', 'Product'),
                'quantity' => Arr::get($product, 'quantity', 1),
                'price' => Arr::get($product, 'price', 0) / 100,
                'total' => (Arr::get($product, 'price', 0) * Arr::get($product, 'quantity', 1)) / 100,
            ];
        }

        return $items;
    }

    private function generatePaymentNotes(PurchasePaid $event): string
    {
        $notes = [];

        if ($event->getReference()) {
            $notes[] = 'Reference: ' . $event->getReference();
        }

        if ($event->getPaymentMethod()) {
            $notes[] = 'Payment Method: ' . ucfirst((string) $event->getPaymentMethod());
        }

        return implode("\n", $notes);
    }

    private function generateRefundNotes(PaymentRefunded $event, ?Doc $originalInvoice): string
    {
        $notes = ['Credit Note - Refund'];

        if ($originalInvoice !== null) {
            $notes[] = 'Original Invoice: #' . $originalInvoice->doc_number;
        }

        if ($event->getReference()) {
            $notes[] = 'Reference: ' . $event->getReference();
        }

        return implode("\n", $notes);
    }
}
