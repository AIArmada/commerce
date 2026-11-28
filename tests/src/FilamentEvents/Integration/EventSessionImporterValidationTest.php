<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Features\Catalog\Actions\ImportEventSessionAction;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use Illuminate\Validation\ValidationException;

it('validates imported session fields and interprets naive times in the supplied timezone', function (): void {
    $owner = User::factory()->create();

    OwnerContext::withOwner($owner, function () use ($owner): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $action = app(ImportEventSessionAction::class);
        $data = [
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'title' => 'Imported Session',
            'starts_at' => '2027-01-01 09:00:00',
            'ends_at' => '2027-01-01 10:00:00',
            'timezone' => 'Asia/Kuala_Lumpur',
            'status' => 'scheduled',
            'capacity' => '25',
        ];

        foreach ([
            ['capacity' => '-1'],
            ['sort_order' => '1.5'],
            ['timezone' => 'Invalid/Zone'],
            ['starts_at' => 'not a date'],
            ['ends_at' => '2027-01-01 08:00:00'],
            ['status' => 'invalid'],
        ] as $invalid) {
            expect(fn () => $action->handle(array_replace($data, $invalid), $owner))
                ->toThrow(ValidationException::class);
        }

        expect(EventSession::query()->where('event_id', $event->id)->count())->toBe(0);

        $session = $action->handle($data, $owner)->refresh();

        expect($session->starts_at->utc()->format('Y-m-d H:i:s'))->toBe('2027-01-01 01:00:00')
            ->and($session->ends_at->utc()->format('Y-m-d H:i:s'))->toBe('2027-01-01 02:00:00')
            ->and($session->capacity)->toBe(25);
    });
});
