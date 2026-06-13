<?php

declare(strict_types=1);

use AIArmada\Engagement\Notifications\EngagementReminderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

test('EngagementReminderNotification → it implements ShouldQueue', function (): void {
    $notification = new EngagementReminderNotification;

    expect($notification)->toBeInstanceOf(ShouldQueue::class);
});
