<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Actions;

use AIArmada\Events\Contracts\EventScopeCascadeDeleter;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Platform\ExtensionRegistry;
use Illuminate\Support\Facades\DB;

final class DeleteRegistrationDependents
{
    public function __construct(
        private readonly ExtensionRegistry $extensions,
    ) {}

    public function handle(string $registrationId, ?string $morphClass = null): void
    {
        /** @var EventRegistration|null $registration */
        $registration = EventRegistration::query()
            ->withoutOwnerScope()
            ->whereKey($registrationId)
            ->first();

        if (! $registration instanceof EventRegistration) {
            return;
        }

        $type = $morphClass ?? $registration->getMorphClass();

        DB::transaction(function () use ($registration, $type): void {
            foreach (['participants', 'answers', 'items'] as $relation) {
                $registration->{$relation}()->chunkById(200, function (iterable $children): void {
                    foreach ($children as $child) {
                        $child->delete();
                    }
                });
            }

            EventRegistration::query()
                ->where('parent_registration_id', $registration->getKey())
                ->chunkById(200, function (iterable $children): void {
                    foreach ($children as $child) {
                        $child->delete();
                    }
                });

            foreach ($this->extensions->resolve('events.cascade-delete', EventScopeCascadeDeleter::class) as $deleter) {
                $deleter->deleteForScope('registration', (string) $registration->getKey(), $type);
            }
        });
    }
}
