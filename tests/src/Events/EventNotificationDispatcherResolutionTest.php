<?php

declare(strict_types=1);

use AIArmada\Communications\Contracts\CommunicationManager;
use AIArmada\Events\Services\EventNotificationDispatcher;

it('resolves the event notification dispatcher with its communications contract', function (): void {
    $dispatcher = app(EventNotificationDispatcher::class);

    expect($dispatcher)->toBeInstanceOf(EventNotificationDispatcher::class)
        ->and(app(CommunicationManager::class))->toBeInstanceOf(CommunicationManager::class);
});
