<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\RecipientRouting\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Features\RecipientRouting\Models\CommunicationPreference;
use AIArmada\Communications\Features\RecipientRouting\Queries\RecipientReferenceGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class SaveRecipientPreference
{
    public function __construct(private RecipientReferenceGuard $recipients) {}

    /** @param array<string, mixed> $attributes */
    public function create(Model $recipient, array $attributes): CommunicationPreference
    {
        $record = new CommunicationPreference;

        return $record->getConnection()->transaction(function () use ($recipient, $attributes, $record): CommunicationPreference {
            $recipient = $this->recipients->resolve($recipient);
            $record->fill($this->attributes($attributes));
            $record->recipient()->associate($recipient);

            $this->validate($record);
            $record->save();

            return $record;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Model $recipient, CommunicationPreference $record, array $attributes): CommunicationPreference
    {
        return $record->getConnection()->transaction(function () use ($recipient, $attributes, $record): CommunicationPreference {
            $recipient = $this->recipients->resolve($recipient);

            if (! $record->exists || $record->getKey() === null) {
                throw new InvalidArgumentException('Routing record must be persisted.');
            }

            if (CommunicationPreference::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(CommunicationPreference::class, $record->getKey());
            }

            $locked = CommunicationPreference::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->recipient_type !== $recipient->getMorphClass() || (string) $locked->recipient_id !== (string) $recipient->getKey()) {
                throw new AuthorizationException('Routing record does not belong to this recipient.');
            }

            $locked->fill($this->attributes($attributes));

            $this->validate($locked);
            $locked->save();

            return $locked;
        });
    }

    private function validate(CommunicationPreference $record): void
    {
        if ($record->channel !== null && mb_trim($record->channel) === '') {
            throw new InvalidArgumentException('Channel must be non-empty when specified.');
        }

        if (($record->scope_type === null) !== ($record->scope_key === null)) {
            throw new InvalidArgumentException('Scope type and key must both be present or both be null.');
        }
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function attributes(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip(['channel', 'category', 'scope_type', 'scope_key', 'locale', 'timezone', 'quiet_hours_start', 'quiet_hours_end', 'quiet_hours_timezone', 'enabled_at', 'disabled_at', 'opted_in_at', 'opted_out_at', 'verified_at', 'source', 'metadata']));
    }
}
