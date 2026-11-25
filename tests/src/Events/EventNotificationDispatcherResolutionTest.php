<?php

declare(strict_types=1);

use AIArmada\Communications\Contracts\CommunicationManager;
use AIArmada\Events\Contracts\EventChangeNoticeNotificationDispatcher;
use AIArmada\Events\Drivers\EventChangeNoticeNotificationDriver;

it('resolves the event notification dispatcher with its communications contract', function (): void {
    $dispatcher = app(EventChangeNoticeNotificationDispatcher::class);

    expect($dispatcher)->toBeInstanceOf(EventChangeNoticeNotificationDriver::class)
        ->and(app(CommunicationManager::class))->toBeInstanceOf(CommunicationManager::class);
});
