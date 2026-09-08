<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Traits;

use AIArmada\Engagement\Models\Reminder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait CanSetReminders
{
    use InteractsWithEngagement;

    /**
     * @return MorphMany<Reminder, $this>
     */
    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'recipient');
    }

    public function setReminder(mixed $subject, string $type, array $options = []): Reminder
    {
        return $this->engagementReminderManager()->setReminder($this->engagementActor(), $subject, $type, $options);
    }

    public function cancelReminder(mixed $subject, string $type): void
    {
        $this->engagementReminderManager()->cancelReminder($this->engagementActor(), $subject, $type);
    }
}
