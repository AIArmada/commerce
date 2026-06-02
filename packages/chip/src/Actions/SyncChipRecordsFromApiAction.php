<?php

declare(strict_types=1);

namespace AIArmada\Chip\Actions;

use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Facades\Chip;
use AIArmada\Chip\Listeners\StoreWebhookData;
use AIArmada\Chip\Models\Purchase;
use Throwable;

class SyncChipRecordsFromApiAction
{
    public function __construct(
        private readonly StoreWebhookData $storeWebhookData,
        private readonly LinkChipCustomerFromCheckout $linkChipCustomerFromCheckout,
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
                // The command links customers only while re-syncing the CHIP payload; live checkout completion calls the bridge directly.
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
                $this->linkChipCustomerFromCheckout->handle($purchaseId, $payload, 'chip_sync_from_api');
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
}
