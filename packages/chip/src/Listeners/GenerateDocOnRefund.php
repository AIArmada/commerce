<?php

declare(strict_types=1);

namespace AIArmada\Chip\Listeners;

use AIArmada\Chip\Actions\RunChipPurchaseDocGenerationAction;
use AIArmada\Chip\Events\PaymentRefunded;
use AIArmada\Chip\Models\Purchase;
use AIArmada\Chip\Support\BuildChipDocData;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\App;

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

        $purchase = Purchase::find($purchaseId);

        if ($purchase === null) {
            return;
        }

        $originalInvoice = $this->findOriginalInvoice($purchase);

        $docData = App::make(BuildChipDocData::class)->forRefund($purchase, $event, $docType, $originalInvoice);

        App::make(RunChipPurchaseDocGenerationAction::class)->execute(
            purchaseId: $purchaseId,
            payload: $event->payload,
            docData: $docData,
            docTypeConfigKey: 'chip.integrations.docs.refund_doc_type',
        );
    }

    private function findOriginalInvoice(Purchase $purchase): ?Doc
    {
        if (! class_exists(Doc::class)) {
            return null;
        }

        $docType = $this->resolveConfiguredDocType('chip.integrations.docs.paid_doc_type', DocType::Invoice);

        return Doc::query()
            ->where('docable_type', $purchase->getMorphClass())
            ->where('docable_id', $purchase->id)
            ->where('doc_type', $docType?->value)
            ->first();
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
