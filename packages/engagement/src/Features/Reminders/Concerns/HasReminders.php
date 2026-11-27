<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Reminders\Concerns;

use AIArmada\Engagement\Features\Reminders\Models\Reminder;
use AIArmada\Engagement\Traits\ReceivesEngagement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait HasReminders
{
    use ReceivesEngagement;

    /**
     * @return MorphMany<Reminder, $this>
     */
    public function reminders(): MorphMany
    {
        return $this->engagementRelation(Reminder::class, 'remindable');
    }
}
