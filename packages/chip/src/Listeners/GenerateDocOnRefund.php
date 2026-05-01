<?php

declare(strict_types=1);

namespace AIArmada\Chip\Listeners;

use AIArmada\Chip\Events\PaymentRefunded;
use AIArmada\Chip\Models\Purchase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocService;
use AIArmada\Docs\States\DocStatus;
use AIArmada\Docs\States\Paid;
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

        $docType = config('chip.integrations.docs.refund_doc_type', 'credit_note');

        if ($docType === null || $docType === false) {
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

            // Skip if credit note already exists for this purchase
            if ($this->creditNoteExistsForPurchase($purchase)) {
                return;
            }

            $this->generateCreditNote($purchase, $event, (string) $docType);
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

    private function generateCreditNote(Purchase $purchase, PaymentRefunded $event, string $docType): void
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
        string $docType,
        ?Doc $originalInvoice
    ): DocData {
        $amount = $event->getAmount();
        $currency = $event->getCurrency();

        $customerData = [
            'name' => $event->purchase?->client->full_name ?? null,
            'email' => $event->purchase?->client->email ?? null,
        ];

        // Credit note uses negative amounts or references original
        $metadata = [
            'chip_purchase_id' => $event->getPurchaseId(),
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
            docType: $docType,
            status: DocStatus::fromString(Paid::class), // Credit notes are immediately effective
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
            docableType: Purchase::class,
            docableId: (string) $purchase->id,
            generatePdf: config('chip.integrations.docs.generate_pdf', true),
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
            ->where('docable_type', Purchase::class)
            ->where('docable_id', $purchase->id)
            ->where('doc_type', config('chip.integrations.docs.paid_doc_type', 'invoice'))
            ->first();
    }

    private function creditNoteExistsForPurchase(Purchase $purchase): bool
    {
        if (! class_exists(Doc::class)) {
            return false;
        }

        return Doc::query()
            ->where('docable_type', Purchase::class)
            ->where('docable_id', $purchase->id)
            ->where('doc_type', config('chip.integrations.docs.refund_doc_type', 'credit_note'))
            ->exists();
    }
}
