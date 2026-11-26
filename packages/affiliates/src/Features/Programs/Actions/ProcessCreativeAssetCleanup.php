<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Actions;

use AIArmada\Affiliates\Contracts\CreativeAssetFilesystem;
use AIArmada\Affiliates\Contracts\CreativeCleanupQueue;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateCreativeAssetCleanup;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

final readonly class ProcessCreativeAssetCleanup implements CreativeCleanupQueue
{
    private const int CLAIM_LEASE_SECONDS = 300;

    public function __construct(private CreativeAssetFilesystem $filesystem) {}

    public function enqueue(string $mediaClass, array $attributes, int $connectionIdentity): void
    {
        $connection = (new AffiliateCreativeAssetCleanup)->getConnection();

        if (spl_object_id($connection) !== $connectionIdentity || $connection->transactionLevel() < 1) {
            throw new LogicException('Creative cleanup must be recorded in the active media transaction.');
        }

        $record = AffiliateCreativeAssetCleanup::query()->create([
            'media_class' => $mediaClass,
            'media_attributes' => $attributes,
        ]);
        $id = (string) $record->getKey();

        $connection->afterCommit(function () use ($id): void {
            $this->attempt($id);
        });
    }

    public function recoverDetached(string $mediaClass, array $attributes, int $connectionIdentity): void
    {
        $connection = (new AffiliateCreativeAssetCleanup)->getConnection();

        if (spl_object_id($connection) !== $connectionIdentity) {
            throw new LogicException('Creative rollback cleanup requires the original database connection.');
        }

        try {
            $this->filesystem->removeDetached($mediaClass, $attributes, $connectionIdentity);

            return;
        } catch (Throwable $exception) {
            report($exception);
        }

        if ($connection->transactionLevel() > 0) {
            $connection->afterRollBack(function () use ($mediaClass, $attributes, $connectionIdentity): void {
                $this->recoverDetached($mediaClass, $attributes, $connectionIdentity);
            });
        }

        $connection->transaction(function () use ($mediaClass, $attributes, $connectionIdentity): void {
            $this->enqueue($mediaClass, $attributes, $connectionIdentity);
        });
    }

    public function drain(int $limit): int
    {
        $failures = 0;

        $ids = AffiliateCreativeAssetCleanup::query()
            ->where(function (Builder $query): void {
                $query->whereNull('claim_token')
                    ->orWhereNull('claimed_at')
                    ->orWhere('claimed_at', '<=', CarbonImmutable::now()->subSeconds(self::CLAIM_LEASE_SECONDS));
            })
            ->orderBy('attempt_count')->oldest('created_at')->orderBy('id')
            ->limit(max(1, min(1000, $limit)))->pluck('id');

        foreach ($ids as $id) {
            if (! $this->attempt((string) $id)) {
                $failures++;
            }
        }

        return $failures;
    }

    private function attempt(string $id): bool
    {
        try {
            $claim = $this->claim($id);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        if ($claim === null) {
            return true;
        }

        try {
            $this->filesystem->removeDetached($claim['media_class'], $claim['media_attributes'], $claim['connection_identity']);
        } catch (Throwable $exception) {
            report($exception);
            $this->release($id, $claim['token'], $claim['attempt_count']);

            return false;
        }

        return $this->finalize($id, $claim['token']);
    }

    /**
     * @return array{media_class: string, media_attributes: array<string, mixed>, connection_identity: int, token: string, attempt_count: int}|null
     */
    private function claim(string $id): ?array
    {
        $connection = (new AffiliateCreativeAssetCleanup)->getConnection();

        return $connection->transaction(function () use ($id, $connection): ?array {
            $record = AffiliateCreativeAssetCleanup::query()->lockForUpdate()->find($id);

            if ($record === null) {
                return null;
            }

            $now = CarbonImmutable::now();

            if ($record->claim_token !== null
                && $record->claimed_at !== null
                && $record->claimed_at->greaterThan($now->subSeconds(self::CLAIM_LEASE_SECONDS))) {
                return null;
            }

            $token = (string) Str::uuid();

            $record->forceFill([
                'claim_token' => $token,
                'claimed_at' => $now,
            ])->save();

            return [
                'media_class' => $record->media_class,
                'media_attributes' => $record->media_attributes,
                'connection_identity' => spl_object_id($connection),
                'token' => $token,
                'attempt_count' => $record->attempt_count,
            ];
        }, attempts: 3);
    }

    private function release(string $id, string $token, int $attemptCount): void
    {
        try {
            AffiliateCreativeAssetCleanup::query()
                ->whereKey($id)
                ->where('claim_token', $token)
                ->update([
                    'attempt_count' => $attemptCount + 1,
                    'claim_token' => null,
                    'claimed_at' => null,
                ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function finalize(string $id, string $token): bool
    {
        try {
            AffiliateCreativeAssetCleanup::query()
                ->whereKey($id)
                ->where('claim_token', $token)
                ->delete();

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
