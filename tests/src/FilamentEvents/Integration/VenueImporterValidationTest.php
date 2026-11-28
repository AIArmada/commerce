<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Features\Venues\Actions\ImportVenueAction;
use AIArmada\Events\Features\Venues\Models\Venue;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

it('validates venue imports before persisting any catalog records', function (): void {
    $actor = User::factory()->create();
    Gate::before(static fn (): bool => true);

    OwnerContext::withOwner($actor, function () use ($actor): void {
        $action = app(ImportVenueAction::class);
        $data = ['name' => 'Imported Venue', 'venue_type' => 'physical', 'status' => 'active'];

        foreach ([
            ['name' => ''],
            ['name' => str_repeat('x', 256)],
            ['address_id' => 'invalid'],
            ['phone' => ['invalid']],
            ['email' => 'not-an-email'],
        ] as $invalid) {
            expect(fn () => $action->handle(null, array_replace($data, $invalid), $actor))
                ->toThrow(ValidationException::class);
        }

        expect(Venue::query()->where('name', 'Imported Venue')->exists())->toBeFalse();

        $venue = $action->handle(null, [...$data, 'address_id' => '', 'email' => '', 'phone' => ''], $actor);
        expect($venue->name)->toBe('Imported Venue')
            ->and($venue->visibility)->toBe('public')
            ->and($venue->contactMethods()->count())->toBe(0);

        $updated = $action->handle($venue->id, ['name' => 'Updated Venue'], $actor);
        expect($updated->name)->toBe('Updated Venue')
            ->and($updated->status)->toBe('active');
    });
});
