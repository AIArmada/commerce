<?php

declare(strict_types=1);

namespace AIArmada\Events\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventSubmission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use InvalidArgumentException;

final class EventSubmissionOwnerScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! self::enabled()) {
            return;
        }

        $owner = OwnerContext::resolve();

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            sprintf('%s requires an owner context or explicit global context.', $model::class),
        );

        $eventClass = ModelResolver::eventClass();
        $event = new $eventClass;

        $builder->where(function (Builder $query) use ($model, $owner, $event, $eventClass): void {
            $query->whereIn(
                $model->qualifyColumn('event_id'),
                $eventClass::query()->select($event->qualifyColumn($event->getKeyName())),
            )->orWhere(function (Builder $targetQuery) use ($model, $owner): void {
                $targetQuery->whereNull($model->qualifyColumn('event_id'));

                if (! $owner instanceof Model) {
                    $targetQuery
                        ->whereNull($model->qualifyColumn('target_type'))
                        ->whereNull($model->qualifyColumn('target_id'));

                    return;
                }

                $targetQuery->where(function (Builder $ownerQuery) use ($model, $owner): void {
                    $ownerQuery
                        ->where($model->qualifyColumn('target_type'), $owner->getMorphClass())
                        ->where($model->qualifyColumn('target_id'), $owner->getKey());

                    if ((bool) config('events.features.owner.include_global', false)) {
                        $ownerQuery->orWhere(function (Builder $globalQuery) use ($model): void {
                            $globalQuery
                                ->whereNull($model->qualifyColumn('target_type'))
                                ->whereNull($model->qualifyColumn('target_id'));
                        });
                    }
                });
            });
        });
    }

    public static function register(): void
    {
        EventSubmission::addGlobalScope(new self);
        EventSubmission::saving(self::guardSaving(...));
        EventSubmission::deleting(self::guardDeleting(...));
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function supports(string $modelClass): bool
    {
        return $modelClass === EventSubmission::class;
    }

    private static function guardSaving(Model $model): void
    {
        $targetType = $model->getAttribute('target_type');
        $targetId = $model->getAttribute('target_id');

        if (($targetType === null) !== ($targetId === null)) {
            throw new InvalidArgumentException('Submission target type and id must both be present or both be null.');
        }

        if ($model->exists
            && ($model->getOriginal('target_type') !== $targetType
                || (string) $model->getOriginal('target_id') !== (string) $targetId)) {
            throw new InvalidArgumentException('Submission target ownership cannot be reassigned after creation.');
        }

        $eventId = $model->getAttribute('event_id');
        $occurrenceId = $model->getAttribute('event_occurrence_id');
        $sessionId = $model->getAttribute('event_session_id');

        if (($sessionId !== null || $occurrenceId !== null) && $eventId === null) {
            throw new InvalidArgumentException('Event submissions must set event_id when linking an occurrence or session.');
        }

        if ($eventId !== null) {
            EventWriteGuard::findOrFail($eventId);
            self::guardGraphIntegrity($eventId, $occurrenceId, $sessionId);
        }

        if (! self::enabled()) {
            return;
        }

        $owner = OwnerContext::resolve();

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            'Event submissions require an owner context or explicit global context.',
        );

        if ($eventId !== null) {
            return;
        }

        if ($targetType === null && $targetId === null) {
            if (! OwnerContext::isExplicitGlobal()) {
                throw new AuthorizationException('Explicit global owner context is required for global event submissions.');
            }

            return;
        }

        if (! $owner instanceof Model
            || $targetType !== $owner->getMorphClass()
            || (string) $targetId !== (string) $owner->getKey()) {
            throw new AuthorizationException('Cross-owner event submission write blocked.');
        }
    }

    private static function guardDeleting(Model $model): void
    {
        if (! self::enabled()) {
            return;
        }

        $eventId = $model->getAttribute('event_id');

        if ($eventId !== null) {
            $eventClass = ModelResolver::eventClass();
            $exists = $eventClass::query()->withoutOwnerScope()->whereKey($eventId)->exists();

            if ($exists) {
                EventWriteGuard::findOrFail($eventId);

                return;
            }

            $owner = OwnerContext::resolve();

            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                'Event submissions require an owner context or explicit global context.',
            );

            $targetType = $model->getAttribute('target_type');
            $targetId = $model->getAttribute('target_id');

            if ($targetType === null && $targetId === null) {
                if (! OwnerContext::isExplicitGlobal()) {
                    throw new AuthorizationException('Explicit global owner context is required to delete orphaned global event submissions.');
                }

                return;
            }

            if (! $owner instanceof Model
                || $targetType !== $owner->getMorphClass()
                || (string) $targetId !== (string) $owner->getKey()) {
                throw new AuthorizationException('Cross-owner event submission write blocked.');
            }

            return;
        }

        $owner = OwnerContext::resolve();

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            'Event submissions require an owner context or explicit global context.',
        );

        $targetType = $model->getAttribute('target_type');
        $targetId = $model->getAttribute('target_id');

        if ($targetType === null && $targetId === null) {
            if (! OwnerContext::isExplicitGlobal()) {
                throw new AuthorizationException('Explicit global owner context is required for global event submissions.');
            }

            return;
        }

        if (! $owner instanceof Model
            || $targetType !== $owner->getMorphClass()
            || (string) $targetId !== (string) $owner->getKey()) {
            throw new AuthorizationException('Cross-owner event submission write blocked.');
        }
    }

    private static function guardGraphIntegrity(int | string $eventId, mixed $occurrenceId, mixed $sessionId): void
    {
        if ($occurrenceId !== null) {
            $occurrence = EventOccurrence::query()->withoutOwnerScope()->whereKey($occurrenceId)->first();

            if (! $occurrence instanceof EventOccurrence) {
                throw new InvalidArgumentException('Event submissions must link an existing occurrence.');
            }

            if ((string) $occurrence->getAttribute('event_id') !== (string) $eventId) {
                throw new InvalidArgumentException('Event submissions must link an occurrence of the submitted event.');
            }
        }

        if ($sessionId === null) {
            return;
        }

        $session = EventSession::query()->withoutOwnerScope()->whereKey($sessionId)->first();

        if (! $session instanceof EventSession) {
            throw new InvalidArgumentException('Event submissions must link an existing session.');
        }

        if ((string) $session->getAttribute('event_id') !== (string) $eventId) {
            throw new InvalidArgumentException('Event submissions must link a session of the submitted event.');
        }

        if ($occurrenceId !== null) {
            $sessionOccurrenceId = $session->getAttribute('event_occurrence_id');

            if ($sessionOccurrenceId === null || (string) $sessionOccurrenceId !== (string) $occurrenceId) {
                throw new InvalidArgumentException('Event submissions must link a session of the submitted occurrence.');
            }
        }
    }

    private static function enabled(): bool
    {
        return (bool) config('events.features.owner.enabled', true);
    }
}
