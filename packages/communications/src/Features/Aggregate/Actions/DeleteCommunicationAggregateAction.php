<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Models\Communication;
use DateTimeInterface;
use RuntimeException;

final class DeleteCommunicationAggregateAction
{
    public function handle(string $communicationId, ?DateTimeInterface $before = null): bool
    {
        return (new Communication)->getConnection()->transaction(function () use ($communicationId, $before): bool {
            $communication = Communication::query()->whereKey($communicationId)->lockForUpdate()->firstOrFail();

            if (Communication::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(Communication::class, $communicationId);
            }

            if ($before !== null && ! $this->isPrunable($communication, $before)) {
                return false;
            }

            if (! $communication->delete()) {
                throw new RuntimeException('Communication deletion was cancelled.');
            }

            return true;
        });
    }

    private function isPrunable(Communication $communication, DateTimeInterface $before): bool
    {
        foreach (['completed_at', 'failed_at', 'cancelled_at', 'expires_at'] as $column) {
            $value = $communication->{$column};

            if ($value !== null && $value->lt($before)) {
                return true;
            }
        }

        return false;
    }
}
