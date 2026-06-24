<?php

declare(strict_types=1);

namespace AIArmada\Communications\Traits;

use AIArmada\Communications\Models\NotificationInbox;
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
        $this->notificationInboxes()
            ->where('id', $inboxId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function markAllAsRead(): void
    {
        $this->notificationInboxes()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function archiveRead(): void
    {
        $this->notificationInboxes()
            ->whereNotNull('read_at')
            ->whereNull('archived_at')
            ->where('created_at', '<', now()->subDays(30))
            ->update(['archived_at' => now()]);
    }
}
