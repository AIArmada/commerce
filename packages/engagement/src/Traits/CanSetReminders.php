<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Traits;

use AIArmada\Engagement\Contracts\ReminderManager;
use AIArmada\Engagement\Models\Reminder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait CanSetReminders
{
    /**
     * @return MorphMany<Reminder, $this>
     */
    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'recipient');
    }

    public function setReminder(mixed $subject, string $type, array $options = []): Reminder
    {
        return app(ReminderManager::class)->setReminder($this, $subject, $type, $options);
    }

    public function cancelReminder(mixed $subject, string $type): void
    {
        app(ReminderManager::class)->cancelReminder($this, $subject, $type);
    }
}
