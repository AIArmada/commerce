<?php

declare(strict_types=1);

namespace AIArmada\Chip\Actions;

use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Facades\Chip;
use AIArmada\Chip\Listeners\StoreWebhookData;
use AIArmada\Chip\Models\ChipCustomerLink;
use AIArmada\Chip\Models\Purchase;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class SyncChipRecordsFromApiAction
{
    public function __construct(
        private readonly StoreWebhookData $storeWebhookData,
    ) {}

    /**
     * @param  array<int, string>  $purchaseIds
     * @param  array<int, string>  $statuses
     * @return array{processed:int,synced:int,skipped:int,failed:int,errors:array<int, string>}
     */
    public function handle(
        array $purchaseIds,
        bool $dryRun = false,
        bool $overwriteExisting = false,
        array $statuses = [],
        ?callable $onProgress = null,
    ): array {
        $summary = [
            'processed' => 0,
            'synced' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $ids = collect($purchaseIds)
            ->filter(static fn (mixed $id): bool => is_string($id) && $id !== '')
            ->unique()
            ->values();

        $normalizedStatuses = collect($statuses)
            ->filter(static fn (mixed $status): bool => is_string($status) && $status !== '')
            ->map(static fn (string $status): string => mb_strtolower(mb_trim($status)))
            ->values();

        foreach ($ids as $purchaseId) {
            $summary['processed']++;

            if (! $dryRun && ! $overwriteExisting && Purchase::query()->whereKey($purchaseId)->exists()) {
                $summary['skipped']++;

                continue;
            }

            try {
                $remotePurchase = Chip::getPurchase($purchaseId);
                $payload = $this->toPayload($remotePurchase);
                $remoteStatus = mb_strtolower((string) ($payload['status'] ?? ''));

                if ($normalizedStatuses->isNotEmpty() && ! $normalizedStatuses->contains($remoteStatus)) {
                    $summary['skipped']++;

                    continue;
                }

                if (($payload['event_type'] ?? null) === null && ($payload['status'] ?? null) !== null) {
                    $payload['event_type'] = 'purchase.' . (string) $payload['status'];
                }

                if (($payload['event_type'] ?? null) === null) {
                    $payload['event_type'] = 'purchase.updated';
                }

                if ($dryRun) {
                    $summary['synced']++;

                    continue;
                }

                $this->storeWebhookData->handle(WebhookReceived::fromPayload($payload));
                $this->linkChipCustomer($purchaseId, $payload);
                $summary['synced']++;
            } catch (Throwable $throwable) {
                $summary['failed']++;
                $summary['errors'][] = sprintf('%s: %s', $purchaseId, $throwable->getMessage());
            } finally {
                if ($onProgress !== null) {
                    $onProgress();
                }
            }
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayload(mixed $remotePurchase): array
    {
        if (is_array($remotePurchase)) {
            return $remotePurchase;
        }

        if (is_object($remotePurchase) && method_exists($remotePurchase, 'toArray')) {
            /** @var array<string, mixed> $payload */
            $payload = $remotePurchase->toArray();

            return $payload;
        }

        return (array) $remotePurchase;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function linkChipCustomer(string $purchaseId, array $payload): void
    {
        $checkoutSessionModel = 'AIArmada\\Checkout\\Models\\CheckoutSession';
        $customerModel = 'AIArmada\\Customers\\Models\\Customer';

        if (! class_exists($checkoutSessionModel) || ! class_exists($customerModel)) {
            return;
        }

        $chipCustomerId = $payload['client_id'] ?? null;

        if (! is_string($chipCustomerId) || $chipCustomerId === '') {
            return;
        }

        /** @var Model|null $checkoutSession */
        $checkoutSession = $checkoutSessionModel::query()
            ->where('payment_id', $purchaseId)
            ->whereNotNull('customer_id')
            ->latest('created_at')
            ->first();

        $customerId = $checkoutSession?->getAttribute('customer_id');

        if (! is_string($customerId) || $customerId === '') {
            return;
        }

        /** @var Model|null $customer */
        $customer = $customerModel::query()->find($customerId);

        if ($customer === null) {
            return;
        }

        if (! $this->isChipCustomerBridgeEnabled($customer)) {
            return;
        }

        ChipCustomerLink::query()->updateOrCreate(
            [
                'subject_type' => $customer->getMorphClass(),
                'subject_id' => (string) $customer->getKey(),
            ],
            [
                'chip_customer_id' => $chipCustomerId,
                'owner_type' => $customer->getAttribute('owner_type'),
                'owner_id' => $customer->getAttribute('owner_id'),
                'metadata' => [
                    'source' => 'chip_sync_from_api',
                    'checkout_session_id' => (string) $checkoutSession->getKey(),
                    'chip_purchase_id' => $purchaseId,
                ],
            ],
        );
    }

    private function isChipCustomerBridgeEnabled(Model $customer): bool
    {
        return method_exists($customer, 'chipCustomerLink')
            && method_exists($customer, 'chipId')
            && method_exists($customer, 'createAsChipCustomer');
    }
}
