<?php

declare(strict_types=1);

namespace AIArmada\Chip\Actions;

use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Facades\Chip;
use AIArmada\Chip\Listeners\StoreWebhookData;
use AIArmada\Chip\Models\Purchase;
use AIArmada\Chip\Support\ChipCustomerBridge;
use Throwable;

class SyncChipRecordsFromApiAction
{
    public function __construct(
        private readonly StoreWebhookData $storeWebhookData,
        private readonly ChipCustomerBridge $customerBridge,
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
                $checkoutSession = $this->customerBridge->findCheckoutSessionByPaymentId($purchaseId);

                if ($checkoutSession !== null) {
                    $this->linkCustomer($purchaseId, $checkoutSession);
                }

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
                $this->linkCustomer($purchaseId, null, $payload);
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
     * @param  array<string, mixed>|null  $payload
     */
    private function linkCustomer(string $purchaseId, mixed $checkoutSession, ?array $payload = null): void
    {
        if ($checkoutSession === null && $payload === null) {
            return;
        }

        if ($checkoutSession !== null) {
            $this->customerBridge->linkCustomer($checkoutSession, [], 'chip_sync_from_api');

            return;
        }

        if ($payload !== null) {
            $session = $this->customerBridge->findCheckoutSessionByPaymentId($purchaseId);

            if ($session !== null) {
                $this->customerBridge->linkCustomer($session, $payload, 'chip_sync_from_api');
            }
        }
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
}
