<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Events\EventRegistrationCancelled;
use AIArmada\Events\Models\EventRegistration;

final class CancelBundleChildrenOnParentCanceled
{
    public function handle(EventRegistrationCancelled $event): void
    {
        $registration = $event->registration;

        if (! $registration->is_bundle_root) {
            return;
        }

        $children = EventRegistration::query()
            ->where('parent_registration_id', $registration->getKey())
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->get();

        if ($children->isEmpty()) {
            return;
        }

        $ev = $registration->event;

        OwnerContext::withOwner($ev->owner ?? null, function () use ($children): void {
            foreach ($children as $child) {
                app(RegistrationServiceInterface::class)
                    ->cancel($child, 'Parent registration cancelled');
            }
        });
    }
}
