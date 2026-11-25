<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\EntryPoints\Listeners;

use AIArmada\Events\Events\EventRegistrationCancelled;
use AIArmada\Events\Features\Registrations\Actions\CancelBundleChildrenOnParentCanceledAction;

final class CancelBundleChildrenOnParentCanceled
{
    public function __construct(
        private readonly CancelBundleChildrenOnParentCanceledAction $cancelChildren,
    ) {}

    public function handle(EventRegistrationCancelled $event): void
    {
        $this->cancelChildren->handle($event->registrationId, $event->eventId);
    }
}
