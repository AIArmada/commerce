<?php

declare(strict_types=1);

namespace AIArmada\Chip\Listeners;

use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Models\Purchase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocService;
use AIArmada\Docs\States\DocStatus;
use AIArmada\Docs\States\Paid;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Generates invoice/receipt document when a purchase is paid.
 */
final class GenerateDocOnPayment implements ShouldQueue
{
    public function handle(PurchasePaid $event): void
    {
        if (! class_exists(DocService::class)) {
            return;
        }

        $docType = $this->resolveConfiguredDocType('chip.integrations.docs.paid_doc_type', DocType::Invoice);

        if (! $docType instanceof DocType) {
            return;
        }

        $runner = function () use ($event, $docType): void {
            // Find the persisted Purchase model
            $purchase = Purchase::find($event->getPurchaseId());

            if ($purchase === null) {
                return;
            }

            // Skip if doc already exists for this purchase
            if ($this->docExistsForPurchase($purchase)) {
                return;
            }

            $this->generateDoc($purchase, $event, $docType);
        };

        if (! (bool) config('chip.owner.enabled', false)) {
            $runner();

            return;
        }

        $owner = $this->resolveOwnerFromPayload($event->payload);

        if ($owner === null) {
            return;
        }

        OwnerContext::withOwner($owner, $runner);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveOwnerFromPayload(array $payload): ?Model
    {
        $ownerType = Arr::get($payload, '__owner_type');
        $ownerId = Arr::get($payload, '__owner_id');

        if (! is_string($ownerType)) {
            return null;
        }

        if (! is_string($ownerId) && ! is_int($ownerId)) {
            return null;
        }

        return OwnerContext::fromTypeAndId($ownerType, $ownerId);
    }

    private function generateDoc(Purchase $purchase, PurchasePaid $event, DocType $docType): void
    {
        /** @var DocService $docService */
        $docService = app(DocService::class);

        $docData = $this->buildDocData($purchase, $event, $docType);
        $docService->create($docData);
    }

    private function buildDocData(Purchase $purchase, PurchasePaid $event, DocType $docType): DocData
    {
        $customerData = $this->extractCustomerData($purchase, $event);
        $items = $this->extractItems($purchase, $event);
        $amount = $event->getAmount();
        $currency = $event->getCurrency();

        // Determine status - since payment is already complete, mark as Paid
        $status = DocStatus::fromString(Paid::class);

        return new DocData(
            docType: $docType->value,
            status: $status,
            issueDate: now(),
            dueDate: null, // Already paid
            subtotal: $amount / 100, // Convert from cents
            taxAmount: 0, // Can be extracted from metadata if needed
            discountAmount: 0,
            total: $amount / 100,
            currency: $currency,
            customerData: $customerData,
            items: $items,
            notes: $this->generateNotes($purchase, $event),
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

    /**
     * @return array<string, mixed>
     */
    private function extractCustomerData(Purchase $purchase, PurchasePaid $event): array
    {
        return array_filter([
            'name' => Arr::get($purchase->client, 'full_name')
                ?? Arr::get($purchase->client, 'legal_name')
                ?? $event->getCustomerName(),
            'email' => Arr::get($purchase->client, 'email') ?? $event->getCustomerEmail(),
            'phone' => Arr::get($purchase->client, 'phone'),
            'address' => Arr::get($purchase->client, 'street_address'),
            'city' => Arr::get($purchase->client, 'city'),
            'state' => Arr::get($purchase->client, 'state'),
            'postcode' => Arr::get($purchase->client, 'zip_code'),
            'country' => Arr::get($purchase->client, 'country'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * Extract items from purchase data.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractItems(Purchase $purchase, PurchasePaid $event): array
    {
        $purchaseData = $purchase->purchase ?? [];
        $products = Arr::get($purchaseData, 'products', []);

        if (empty($products)) {
            // Fallback to single line item
            return [
                [
                    'name' => Arr::get($purchaseData, 'description', 'Payment'),
                    'description' => Arr::get($purchaseData, 'description', 'Payment'),
                    'quantity' => 1,
                    'price' => $event->getAmount() / 100,
                    'total' => $event->getAmount() / 100,
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

    private function generateNotes(Purchase $purchase, PurchasePaid $event): string
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

    private function docExistsForPurchase(Purchase $purchase): bool
    {
        if (! class_exists(Doc::class)) {
            return false;
        }

        return Doc::query()
            ->where('docable_type', $purchase->getMorphClass())
            ->where('docable_id', $purchase->id)
            ->where('doc_type', $this->resolveConfiguredDocType('chip.integrations.docs.paid_doc_type', DocType::Invoice)?->value)
            ->exists();
    }

    private function resolveConfiguredDocType(string $configKey, DocType $fallback): ?DocType
    {
        $configuredType = config($configKey, $fallback->value);

        if (! is_string($configuredType) || $configuredType === '') {
            return null;
        }

        return DocType::tryFrom($configuredType);
    }
}
