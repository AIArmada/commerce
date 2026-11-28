<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Reminders\Queries;

use AIArmada\Engagement\Features\Reminders\Models\Reminder;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class ReadDueRemindersQuery
{
    /** @return Builder<Reminder> */
    public function query(DateTimeInterface $at): Builder
    {
        $now = CarbonImmutable::instance($at);

        return Reminder::query()
            ->pending()
            ->where('remind_at', '<=', $now)
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            });
    }

    /** @return Collection<int, Reminder> */
    public function execute(?DateTimeInterface $at = null, ?int $limit = null): Collection
    {
        $limit ??= (int) config('engagement.reminder.batch_size', 100);

        return $this->query($at ?? CarbonImmutable::now())
            ->orderBy('remind_at')
            ->orderBy('id')
            ->limit(max(0, $limit))
            ->get();
    }
}
