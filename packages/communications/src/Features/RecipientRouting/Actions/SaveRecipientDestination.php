<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\RecipientRouting\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Features\RecipientRouting\Models\CommunicationDestination;
use AIArmada\Communications\Features\RecipientRouting\Queries\RecipientReferenceGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class SaveRecipientDestination
{
    public function __construct(private RecipientReferenceGuard $recipients) {}

    /** @param array<string, mixed> $attributes */
    public function create(Model $recipient, array $attributes, string $status = 'active'): CommunicationDestination
    {
        $record = new CommunicationDestination;

        return $record->getConnection()->transaction(function () use ($recipient, $attributes, $record, $status): CommunicationDestination {
            $recipient = $this->recipients->resolve($recipient);
            $record->fill($this->attributes($attributes));
            $record->recipient()->associate($recipient);
            $record->status = $status;

            $this->validate($record);
            $record->save();

            return $record;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Model $recipient, CommunicationDestination $record, array $attributes, ?string $status = null): CommunicationDestination
    {
        return $record->getConnection()->transaction(function () use ($recipient, $attributes, $record, $status): CommunicationDestination {
            $recipient = $this->recipients->resolve($recipient);

            if (! $record->exists || $record->getKey() === null) {
                throw new InvalidArgumentException('Routing record must be persisted.');
            }

            if (CommunicationDestination::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(CommunicationDestination::class, $record->getKey());
            }

            $locked = CommunicationDestination::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->recipient_type !== $recipient->getMorphClass() || (string) $locked->recipient_id !== (string) $recipient->getKey()) {
                throw new AuthorizationException('Routing record does not belong to this recipient.');
            }

            $locked->fill($this->attributes($attributes));
            if ($status !== null) {
                $locked->status = $status;
            }

            $this->validate($locked);
            $locked->save();

            return $locked;
        });
    }

    private function validate(CommunicationDestination $record): void
    {
        if (mb_trim((string) $record->channel) === '') {
            throw new InvalidArgumentException('Channel is required.');
        }
        if (mb_trim((string) $record->status) === '') {
            throw new InvalidArgumentException('Destination status is required.');
        }

    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function attributes(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip(['channel', 'address', 'external_id', 'is_primary', 'verified_at', 'metadata', 'platform', 'app_version', 'device_label', 'locale', 'timezone', 'last_seen_at']));
    }
}
