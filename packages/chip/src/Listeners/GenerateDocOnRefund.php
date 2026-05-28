<?php

declare(strict_types=1);

namespace AIArmada\Chip\Listeners;

use AIArmada\Chip\Events\PaymentRefunded;
use AIArmada\Chip\Models\Purchase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocService;
use AIArmada\Docs\States\DocStatus;
use AIArmada\Docs\States\Refunded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Generates credit note document when a payment is refunded.
 */
final class GenerateDocOnRefund implements ShouldQueue
{
    public function handle(PaymentRefunded $event): void
    {
        if (! class_exists(DocService::class)) {
            return;
        }

        $docType = $this->resolveConfiguredDocType('chip.integrations.docs.refund_doc_type', DocType::CreditNote);

        if (! $docType instanceof DocType) {
            return;
        }

        $purchaseId = $event->getPurchaseId();

        if ($purchaseId === null) {
            return;
        }

        $runner = function () use ($purchaseId, $event, $docType): void {
            // Find the persisted Purchase model
            $purchase = Purchase::find($purchaseId);

            if ($purchase === null) {
                return;
            }

            if ($this->creditNoteExistsForRefund($purchase, $event, $docType)) {
                return;
            }

            $this->generateCreditNote($purchase, $event, $docType);
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

    private function generateCreditNote(Purchase $purchase, PaymentRefunded $event, DocType $docType): void
    {
        /** @var DocService $docService */
        $docService = app(DocService::class);

        // Find original invoice to reference
        $originalInvoice = $this->findOriginalInvoice($purchase);

        $docData = $this->buildDocData($purchase, $event, $docType, $originalInvoice);
        $docService->create($docData);
    }

    private function buildDocData(
        Purchase $purchase,
        PaymentRefunded $event,
        DocType $docType,
        ?Doc $originalInvoice
    ): DocData {
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

        // Credit note uses negative amounts or references original
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

        $notes = $this->generateNotes($purchase, $event, $originalInvoice);

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
                    'description' => 'Refund' . (($originalInvoice !== null) ? ' for Invoice #' . $originalInvoice->doc_number : ''),
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

    private function generateNotes(Purchase $purchase, PaymentRefunded $event, ?Doc $originalInvoice): string
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

    private function findOriginalInvoice(Purchase $purchase): ?Doc
    {
        if (! class_exists(Doc::class)) {
            return null;
        }

        return Doc::query()
            ->where('docable_type', $purchase->getMorphClass())
            ->where('docable_id', $purchase->id)
            ->where('doc_type', $this->resolveConfiguredDocType('chip.integrations.docs.paid_doc_type', DocType::Invoice)?->value)
            ->first();
    }

    private function creditNoteExistsForRefund(Purchase $purchase, PaymentRefunded $event, DocType $docType): bool
    {
        if (! class_exists(Doc::class)) {
            return false;
        }

        $query = Doc::query()
            ->where('docable_type', $purchase->getMorphClass())
            ->where('docable_id', $purchase->id)
            ->where('doc_type', $docType->value);

        $paymentId = $event->payment?->getPaymentId();

        if ($paymentId !== null && $paymentId !== '') {
            return $query
                ->where('metadata->chip_payment_id', $paymentId)
                ->exists();
        }

        return $query->exists();
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
