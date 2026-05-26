<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\Events\Actions\SyncEventOrderCompletionAction;
use AIArmada\Events\Events\RegistrationCheckedIn;

final class SyncEventOrderCompletionOnRegistrationCheckedIn
{
    public function __construct(
        private readonly SyncEventOrderCompletionAction $syncEventOrderCompletion,
    ) {}

    public function handle(RegistrationCheckedIn $event): void
    {
        $this->syncEventOrderCompletion->handleCheckedInRegistration($event);
    }
}
