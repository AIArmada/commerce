<?php

declare(strict_types=1);

namespace AIArmada\Chip\Support;

use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Models\Purchase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use DateTimeInterface;

final class PurchaseIdempotencyLedger
{
    private const METADATA_KEY = 'chip_idempotency';

    public function find(string $brandId, string $idempotencyKey, string $fingerprint): ?PurchaseData
    {
        $ledger = $this->findLedger($brandId, $idempotencyKey);

        if ($ledger === null) {
            return null;
        }

        $entry = $this->entry($ledger);
        $this->assertFingerprint($entry, $fingerprint);

        $response = $entry['response'] ?? null;
        if ($response === null) {
            if ($this->isExpiredReservation($ledger, $entry)) {
                $this->deleteLedger($ledger);

                return null;
            }

            throw new ChipValidationException(
                'Purchase idempotency key is already reserved and requires reconciliation.'
            );
        }

        if (! is_array($response)) {
            throw new ChipValidationException('Stored idempotent purchase response is invalid.');
        }

        /** @var array<string, mixed> $response */
        return PurchaseData::from($response);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function reserve(
        string $brandId,
        string $idempotencyKey,
        string $fingerprint,
        array $data,
    ): Purchase {
        $this->assertOwnerContext();

        $existing = $this->findLedger($brandId, $idempotencyKey);

        if ($existing !== null) {
            $existingEntry = $this->entry($existing);

            if (($existingEntry['response'] ?? null) !== null || ! $this->isExpiredReservation($existing, $existingEntry)) {
                throw new ChipValidationException(
                    'Purchase idempotency key is already reserved and requires reconciliation.'
                );
            }

            // A crashed reservation left a stub behind; the key expired, so
            // release it instead of bricking the key forever.
            $this->deleteLedger($existing);
        }

        $timestamp = time();
        $ledger = new Purchase;
        $ledger->forceFill([
            'type' => 'purchase',
            'created_on' => $timestamp,
            'updated_on' => $timestamp,
            'client' => is_array($data['client'] ?? null) ? $data['client'] : [],
            'purchase' => is_array($data['purchase'] ?? null) ? $data['purchase'] : [],
            'brand_id' => $brandId,
            'issuer_details' => [],
            'transaction_data' => [],
            'status' => 'pending_execute',
            'status_history' => [],
            'send_receipt' => (bool) ($data['send_receipt'] ?? false),
            'is_test' => (bool) ($data['is_test'] ?? false),
            'is_recurring_token' => (bool) ($data['is_recurring_token'] ?? false),
            'skip_capture' => (bool) ($data['skip_capture'] ?? false),
            'force_recurring' => (bool) ($data['force_recurring'] ?? false),
            'refund_availability' => 'all',
            'refundable_amount' => 0,
            'platform' => (string) ($data['platform'] ?? 'api'),
            'product' => (string) ($data['product'] ?? 'purchases'),
            'metadata' => [
                self::METADATA_KEY => [
                    'idempotency_key' => $idempotencyKey,
                    'fingerprint' => $fingerprint,
                    'response' => null,
                    'reserved_at' => $timestamp,
                ],
            ],
        ]);

        $owner = OwnerContext::resolve();
        if ((bool) config('chip.owner.enabled', false) && $owner !== null) {
            $ledger->assignOwner($owner);
        }

        Purchase::withoutEvents(function () use ($ledger): void {
            $ledger->save();
        });

        return $ledger;
    }

    public function record(Purchase $ledger, PurchaseData $purchase): void
    {
        $this->assertOwnerContext();

        $ledgerMetadata = $ledger->getAttribute('metadata');
        $metadata = is_array($ledgerMetadata) ? $ledgerMetadata : [];
        $entry = $this->entry($ledger);
        $entry['response'] = $purchase->toArray();
        $metadata[self::METADATA_KEY] = $entry;

        $ledger->forceFill([
            'metadata' => $metadata,
            'status' => $purchase->status,
            'updated_on' => $purchase->updated_on,
        ]);

        Purchase::withoutEvents(function () use ($ledger): void {
            $ledger->save();
        });
    }

    private function findLedger(string $brandId, string $idempotencyKey): ?Purchase
    {
        $this->assertOwnerContext();

        return Purchase::query()
            ->where('brand_id', $brandId)
            ->where('metadata->chip_idempotency->idempotency_key', $idempotencyKey)
            ->first();
    }

    /**
     * Delete unrecorded reservations older than the idempotency TTL.
     */
    public function pruneExpiredReservations(int $limit = 1000): int
    {
        $this->assertOwnerContext();

        $pruned = 0;

        $this->eachExpiredStub(function (Purchase $stub) use (&$pruned, $limit): bool {
            if ($pruned >= $limit) {
                return false;
            }

            $this->deleteLedger($stub);
            $pruned++;

            return true;
        });

        return $pruned;
    }

    public function countExpiredReservations(int $limit = 1000): int
    {
        $this->assertOwnerContext();

        $count = 0;

        $this->eachExpiredStub(function () use (&$count, $limit): bool {
            $count++;

            return $count < $limit;
        });

        return $count;
    }

    /**
     * @param  callable(Purchase): bool  $callback
     */
    private function eachExpiredStub(callable $callback): void
    {
        Purchase::query()
            ->whereNotNull('metadata->chip_idempotency->idempotency_key')
            ->whereNull('metadata->chip_idempotency->response')
            ->chunkById(200, function ($stubs) use ($callback): bool {
                foreach ($stubs as $stub) {
                    if (! $this->isExpiredReservation($stub, $this->entry($stub))) {
                        continue;
                    }

                    if ($callback($stub) === false) {
                        return false;
                    }
                }

                return true;
            }, 'id');
    }

    /**
     * @param  array{idempotency_key: string, fingerprint: string, response: mixed, reserved_at?: mixed}  $entry
     */
    private function isExpiredReservation(Purchase $ledger, array $entry): bool
    {
        $reservedAt = $entry['reserved_at'] ?? null;

        if (is_numeric($reservedAt)) {
            return (time() - (int) $reservedAt) >= $this->reservationTtl();
        }

        $createdAt = $ledger->getAttribute('created_at');

        if ($createdAt instanceof CarbonImmutable || $createdAt instanceof DateTimeInterface) {
            return $createdAt->getTimestamp() <= time() - $this->reservationTtl();
        }

        return false;
    }

    private function reservationTtl(): int
    {
        return max(1, (int) (config('chip.cache.ttl.purchase_idempotency') ?? config('chip.cache.default_ttl', 86400)));
    }

    private function deleteLedger(Purchase $ledger): void
    {
        Purchase::withoutEvents(function () use ($ledger): void {
            $ledger->delete();
        });
    }

    /**
     * @return array{idempotency_key: string, fingerprint: string, response: mixed, reserved_at?: mixed}
     */
    private function entry(Purchase $ledger): array
    {
        $metadata = $ledger->getAttribute('metadata');
        $entry = is_array($metadata) ? ($metadata[self::METADATA_KEY] ?? null) : null;

        if (! is_array($entry)
            || ! is_string($entry['idempotency_key'] ?? null)
            || ! is_string($entry['fingerprint'] ?? null)
            || ! array_key_exists('response', $entry)) {
            throw new ChipValidationException('Stored purchase idempotency ledger entry is invalid.');
        }

        /** @var array{idempotency_key: string, fingerprint: string, response: mixed, reserved_at?: mixed} $entry */
        return $entry;
    }

    /**
     * @param  array{idempotency_key: string, fingerprint: string, response: mixed, reserved_at?: mixed}  $entry
     */
    private function assertFingerprint(array $entry, string $fingerprint): void
    {
        if (hash_equals($fingerprint, $entry['fingerprint'])) {
            return;
        }

        throw new ChipValidationException(
            'Idempotency key has already been used for a different purchase payload.'
        );
    }

    private function assertOwnerContext(): void
    {
        if (! (bool) config('chip.owner.enabled', false)) {
            return;
        }

        OwnerContext::assertResolvedOrExplicitGlobal(
            OwnerContext::resolve(),
            'Purchase idempotency requires an owner context or explicit global context.'
        );
    }
}
