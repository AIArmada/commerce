<?php

declare(strict_types=1);

namespace AIArmada\Chip\Listeners;

use AIArmada\Chip\Actions\RunChipPurchaseDocGenerationAction;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Models\Purchase;
use AIArmada\Chip\Support\BuildChipDocData;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Services\DocService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\App;

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

        $purchase = Purchase::find($event->getPurchaseId());

        if ($purchase === null) {
            return;
        }

        $docData = App::make(BuildChipDocData::class)->forPayment($purchase, $event, $docType);

        App::make(RunChipPurchaseDocGenerationAction::class)->execute(
            purchaseId: $event->getPurchaseId(),
            payload: $event->payload,
            docData: $docData,
            docTypeConfigKey: 'chip.integrations.docs.paid_doc_type',
        );
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
