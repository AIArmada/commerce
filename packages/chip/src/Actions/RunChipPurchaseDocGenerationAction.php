<?php

declare(strict_types=1);

namespace AIArmada\Chip\Actions;

use AIArmada\Chip\Models\Purchase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

final class RunChipPurchaseDocGenerationAction
{
    public function __construct(
        private readonly DocService $docService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        string $purchaseId,
        array $payload,
        DocData $docData,
        string $docTypeConfigKey,
    ): void {
        if (! class_exists(DocService::class)) {
            return;
        }

        $purchase = Purchase::find($purchaseId);

        if ($purchase === null) {
            return;
        }

        $runner = function () use ($purchase, $docData, $payload): void {
            if ($this->docExistsForPayment($purchase, $payload)) {
                return;
            }

            $this->docService->create($docData);
        };

        $owner = $this->resolveOwnerFromPayload($payload);

        if ($owner instanceof Model) {
            OwnerContext::withOwner($owner, $runner);

            return;
        }

        $runner();
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function docExistsForPayment(Purchase $purchase, array $payload): bool
    {
        if (! class_exists(Doc::class)) {
            return false;
        }

        $paymentId = $payload['id'] ?? null;

        if (! is_string($paymentId)) {
            return false;
        }

        return Doc::query()
            ->where('docable_type', $purchase->getMorphClass())
            ->where('docable_id', $purchase->id)
            ->where('metadata->chip_payment_id', $paymentId)
            ->exists();
    }
}
