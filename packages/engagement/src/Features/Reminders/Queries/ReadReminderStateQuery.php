<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Reminders\Queries;

use AIArmada\Engagement\Features\Reminders\Api\ReadsReminderState;
use AIArmada\Engagement\Features\Reminders\Models\Reminder;
use AIArmada\Engagement\Support\EngagementModelGuard;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class ReadReminderStateQuery implements ReadsReminderState
{
    public function remindersFor(mixed $recipient, mixed $subject): array
    {
        $recipient = EngagementModelGuard::identity(EngagementModelGuard::requireModel($recipient, 'recipient'), 'recipient');
        $subject = EngagementModelGuard::identity(EngagementModelGuard::requireModel($subject, 'subject'), 'subject');

        return Reminder::query()
            ->where('recipient_type', $recipient['type'])
            ->where('recipient_id', $recipient['id'])
            ->where('remindable_type', $subject['type'])
            ->where('remindable_id', $subject['id'])
            ->pending()
            ->where(fn (Builder $query): Builder => $query->whereNull('expires_at')->orWhere('expires_at', '>', CarbonImmutable::now()))
            ->orderBy('id')
            ->limit($this->resultLimit())
            ->get()->toArray();
    }

    private function resultLimit(): int
    {
        return max(1, (int) config('engagement.state.result_limit', 100));
    }
}
