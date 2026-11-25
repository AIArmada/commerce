<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Platform\EventOwnerResolver;

final class CancelBundleChildrenOnParentCanceledAction
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrations,
    ) {}

    public function handle(string $registrationId, string $eventId): void
    {
        /** @var EventRegistration|null $registration */
        $registration = EventRegistration::query()->whereKey($registrationId)->first();

        if (! $registration instanceof EventRegistration || ! $registration->is_bundle_root) {
            return;
        }

        $children = EventRegistration::query()
            ->where('parent_registration_id', $registrationId)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        if ($children === []) {
            return;
        }

        OwnerContext::withOwner(EventOwnerResolver::resolve($eventId), function () use ($children): void {
            foreach ($children as $childId) {
                $this->registrations->cancel($childId, 'Parent registration cancelled');
            }
        });
    }
}
