<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Reminders\Concerns;

use AIArmada\Engagement\Features\Reminders\Actions\ManageRemindersAction;
use AIArmada\Engagement\Features\Reminders\Models\Reminder;
use AIArmada\Engagement\Traits\InteractsWithEngagement;
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
        return app(ManageRemindersAction::class)->setReminder($this->engagementActor(), $subject, $type, $options);
    }

    public function cancelReminder(mixed $subject, string $type, array $options = []): void
    {
        app(ManageRemindersAction::class)->cancelReminder($this->engagementActor(), $subject, $type, $options);
    }
}
