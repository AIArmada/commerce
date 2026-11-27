<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Inbox\Traits;

use AIArmada\Communications\Features\Inbox\Actions\ManageInboxEntries;
use AIArmada\Communications\Features\Inbox\Models\NotificationInbox;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasInbox
{
    /**
     * @return MorphMany<NotificationInbox, $this>
     */
    public function notificationInboxes(): MorphMany
    {
        return $this->morphMany(NotificationInbox::class, 'recipient');
    }

    /**
     * @return MorphMany<NotificationInbox, $this>
     */
    public function unreadNotifications(): MorphMany
    {
        return $this->notificationInboxes()->whereNull('read_at');
    }

    public function unreadCount(): int
    {
        return $this->unreadNotifications()->count();
    }

    public function markAsRead(string $inboxId): void
    {
        app(ManageInboxEntries::class)->markAsRead($this->notificationInboxes(), $inboxId);
    }

    public function markAllAsRead(): void
    {
        app(ManageInboxEntries::class)->markAllAsRead($this->notificationInboxes());
    }

    public function archiveRead(): void
    {
        app(ManageInboxEntries::class)->archiveRead($this->notificationInboxes());
    }
}
