<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Inbox\Actions;

use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Enums\NotificationFamily;
use AIArmada\Communications\Enums\NotificationPriority;
use AIArmada\Communications\Enums\NotificationTrigger;
use AIArmada\Communications\Features\Inbox\Models\NotificationInbox;
use AIArmada\Communications\Models\Communication;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class ManageInboxEntries
{
    public function __construct(
        private readonly PayloadRedactor $redactor,
    ) {}

    public function create(
        MorphMany | Model $recipient,
        Communication $communication,
        NotificationFamily $family,
        NotificationPriority $priority,
        NotificationTrigger $trigger,
        string $title,
        ?string $body = null,
        ?array $data = null,
        ?CarbonInterface $scheduledAt = null,
    ): NotificationInbox {
        $recipientModel = $recipient instanceof Model ? $recipient : $recipient->getParent();
        $this->validateOwnedModels(collect([$recipientModel, $communication]));

        return (new NotificationInbox)->getConnection()->transaction(function () use ($recipientModel, $communication, $family, $priority, $trigger, $title, $body, $data, $scheduledAt): NotificationInbox {
            return $this->persist(
                $recipientModel,
                $communication,
                $family,
                $priority,
                $trigger,
                $title,
                $body,
                $data,
                $scheduledAt,
            );
        });
    }

    /**
     * Create inbox entries for multiple recipients in one transaction while
     * retaining model events and observers for every entry.
     *
     * @param  iterable<int, MorphMany|Model>  $recipients
     * @return Collection<int, NotificationInbox>
     */
    public function createMany(
        iterable $recipients,
        Communication $communication,
        NotificationFamily $family,
        NotificationPriority $priority,
        NotificationTrigger $trigger,
        string $title,
        ?string $body = null,
        ?array $data = null,
        ?CarbonInterface $scheduledAt = null,
    ): Collection {
        $recipientModels = collect($recipients)
            ->map(fn (MorphMany | Model $recipient): Model => $recipient instanceof Model ? $recipient : $recipient->getParent())
            ->unique(fn (Model $recipient): string => $recipient->getMorphClass() . ':' . $recipient->getKey())
            ->values();

        $this->validateOwnedModels($recipientModels->concat([$communication]));

        return (new NotificationInbox)->getConnection()->transaction(function () use ($recipientModels, $communication, $family, $priority, $trigger, $title, $body, $data, $scheduledAt): Collection {
            return $recipientModels->map(fn (Model $recipient): NotificationInbox => $this->persist(
                $recipient,
                $communication,
                $family,
                $priority,
                $trigger,
                $title,
                $body,
                $data,
                $scheduledAt,
            ));
        });
    }

    public function countPrunable(?CarbonInterface $before = null): int
    {
        return $this->prunableQuery($before)->count();
    }

    public function markAsRead(MorphMany | Model $recipient, string $id): void
    {
        $this->recipientRelation($recipient)
            ->where('id', $id)
            ->whereNull('read_at')
            ->update(['read_at' => CarbonImmutable::now()]);
    }

    public function markAllAsRead(MorphMany | Model $recipient): void
    {
        $this->recipientRelation($recipient)->whereNull('read_at')->update(['read_at' => CarbonImmutable::now()]);
    }

    public function archive(MorphMany | Model $recipient, string $id): void
    {
        $this->recipientRelation($recipient)
            ->where('id', $id)
            ->whereNull('archived_at')
            ->update(['archived_at' => CarbonImmutable::now()]);
    }

    public function archiveRead(MorphMany | Model $recipient): void
    {
        $this->recipientRelation($recipient)
            ->whereNotNull('read_at')
            ->whereNull('archived_at')
            ->where('created_at', '<', CarbonImmutable::now()->subDays(30))
            ->update(['archived_at' => CarbonImmutable::now()]);
    }

    public function prune(?CarbonInterface $before = null): int
    {
        $pruned = 0;

        $this->prunableQuery($before)->select('id')->chunkById(500, function (Collection $records) use (&$pruned, $before): void {
            $ids = $records->modelKeys();

            if ($ids === []) {
                return;
            }

            $pruned += (new NotificationInbox)->getConnection()->transaction(function () use ($ids, $before): int {
                $lockedIds = $this->prunableQuery($before)
                    ->whereKey($ids)
                    ->lockForUpdate()
                    ->pluck('id')
                    ->all();

                if ($lockedIds === []) {
                    return 0;
                }

                return $this->prunableQuery($before)->whereKey($lockedIds)->delete();
            });
        });

        return $pruned;
    }

    /**
     * @return Builder<NotificationInbox>
     */
    private function prunableQuery(?CarbonInterface $before = null): Builder
    {
        $before ??= CarbonImmutable::now()->subDays(90);

        /** @var OwnerScopeConfig $config */
        $config = NotificationInbox::ownerScopeConfig();

        if (! $config->enabled) {
            return NotificationInbox::query()
                ->whereNotNull('archived_at')
                ->where('archived_at', '<', $before);
        }

        $owner = OwnerContext::resolveConfigured($config);

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            'Pruning inbox entries requires an owner context or explicit global context.',
        );

        return NotificationInbox::query()
            ->forOwner($owner, includeGlobal: false)
            ->whereNotNull('archived_at')
            ->where('archived_at', '<', $before);
    }

    private function recipientRelation(MorphMany | Model $recipient): MorphMany
    {
        $recipientModel = $recipient instanceof Model ? $recipient : $recipient->getParent();
        $this->validateOwnedModels(collect([$recipientModel]));

        return $recipient instanceof MorphMany
            ? $recipient
            : $recipient->morphMany(NotificationInbox::class, 'recipient');
    }

    private function persist(
        Model $recipient,
        Communication $communication,
        NotificationFamily $family,
        NotificationPriority $priority,
        NotificationTrigger $trigger,
        string $title,
        ?string $body,
        ?array $data,
        ?CarbonInterface $scheduledAt,
    ): NotificationInbox {
        $inbox = new NotificationInbox;
        $inbox->recipient_type = $recipient->getMorphClass();
        $inbox->recipient_id = $recipient->getKey();
        $inbox->communication_id = $communication->id;
        $inbox->family = $family;
        $inbox->priority = $priority;
        $inbox->trigger = $trigger;
        $inbox->title = $title;
        $inbox->body = $body;
        $inbox->data = $data !== null ? $this->redactor->redact($data) : null;
        $inbox->scheduled_at = $scheduledAt !== null ? CarbonImmutable::instance($scheduledAt) : null;
        $inbox->save();

        return $inbox;
    }

    /**
     * @param  Collection<int, Model>  $models
     */
    private function validateOwnedModels(Collection $models): void
    {
        foreach ($models as $model) {
            if (! $model->exists || $model->getKey() === null) {
                throw new InvalidArgumentException('Inbox references must be persisted.');
            }
        }

        $models->groupBy(static fn (Model $model): string => $model::class)
            ->each(function (Collection $group, string $modelClass): void {
                if (! method_exists($modelClass, 'ownerScopeConfig')) {
                    return;
                }

                /** @var OwnerScopeConfig $config */
                $config = $modelClass::ownerScopeConfig();

                if (! $config->enabled) {
                    return;
                }

                $owner = OwnerContext::resolveConfigured($config);

                OwnerContext::assertResolvedOrExplicitGlobal(
                    $owner,
                    sprintf('%s requires an owner context or explicit global context.', $modelClass),
                );

                $ids = $group
                    ->map(static fn (Model $model): string => (string) $model->getKey())
                    ->unique()
                    ->values()
                    ->all();

                $found = $modelClass::query()
                    ->forOwner($owner, includeGlobal: false)
                    ->whereKey($ids)
                    ->count();

                if ($found !== count($ids)) {
                    throw new AuthorizationException('Inbox references must belong to the current owner scope.');
                }
            });
    }
}
