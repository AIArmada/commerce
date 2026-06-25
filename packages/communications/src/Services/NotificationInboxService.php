<?php

declare(strict_types=1);

namespace AIArmada\Communications\Services;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Enums\NotificationFamily;
use AIArmada\Communications\Enums\NotificationPriority;
use AIArmada\Communications\Enums\NotificationTrigger;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\NotificationInbox;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class NotificationInboxService
{
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
        $this->validateOwnedModel($recipientModel);
        $this->validateOwnedModel($communication);

        return DB::transaction(function () use ($recipientModel, $communication, $family, $priority, $trigger, $title, $body, $data, $scheduledAt): NotificationInbox {
            $inbox = new NotificationInbox;
            $inbox->recipient_type = $recipientModel->getMorphClass();
            $inbox->recipient_id = $recipientModel->getKey();
            $inbox->communication_id = $communication->id;
            $inbox->family = $family;
            $inbox->priority = $priority;
            $inbox->trigger = $trigger;
            $inbox->title = $title;
            $inbox->body = $body;
            $inbox->data = $data;
            $inbox->scheduled_at = $scheduledAt !== null ? CarbonImmutable::instance($scheduledAt) : null;
            $inbox->save();

            return $inbox;
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
            ->update(['read_at' => now()]);
    }

    public function markAllAsRead(MorphMany $recipient): void
    {
        $recipient->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function archive(MorphMany | Model $recipient, string $id): void
    {
        $this->recipientRelation($recipient)
            ->where('id', $id)
            ->whereNull('archived_at')
            ->update(['archived_at' => now()]);
    }

    public function prune(?CarbonInterface $before = null): int
    {
        $query = $this->prunableQuery($before);
        $count = $query->count();

        if ($count === 0) {
            return 0;
        }

        $query->delete();

        return $count;
    }

    /**
     * @return Builder<NotificationInbox>
     */
    private function prunableQuery(?CarbonInterface $before = null): Builder
    {
        $before ??= CarbonImmutable::now()->subDays(90);

        return NotificationInbox::query()
            ->withoutOwnerScope()
            ->whereNotNull('archived_at')
            ->where('archived_at', '<', $before);
    }

    private function recipientRelation(MorphMany | Model $recipient): MorphMany
    {
        $recipientModel = $recipient instanceof Model ? $recipient : $recipient->getParent();
        $this->validateOwnedModel($recipientModel);

        return $recipient instanceof MorphMany
            ? $recipient
            : $recipient->morphMany(NotificationInbox::class, 'recipient');
    }

    private function validateOwnedModel(Model $model): void
    {
        try {
            OwnerWriteGuard::findOrFailForOwner($model::class, $model->getKey());
        } catch (InvalidArgumentException) {
        }
    }
}
