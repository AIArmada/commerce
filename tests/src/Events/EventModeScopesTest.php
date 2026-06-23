<?php

declare(strict_types=1);

use AIArmada\Events\Enums\PricingMode;
use AIArmada\Events\Enums\RegistrationMode;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventTicketType;

beforeEach(function (): void {
    config()->set('events.features.free_only.auto_derive_pricing_from_ticket_types', true);
    config()->set('events.features.free_only.default_registration_mode', 'required');
    config()->set('events.features.free_only.auto_issue_passes_for_free', true);
});

describe('Event pricing mode', function (): void {
    it('returns explicit override when set', function (): void {
        $event = Event::factory()->free()->create();

        expect($event->effectivePricingMode())->toBe(PricingMode::Free);
        expect($event->isFree())->toBeTrue();
    });

    it('derives free from zero-price ticket types', function (): void {
        $event = Event::factory()->create();
        EventTicketType::factory()->freeTicket()->create(['event_id' => $event->id]);

        expect($event->effectivePricingMode())->toBe(PricingMode::Free);
        expect($event->isFree())->toBeTrue();
    });

    it('derives paid from priced ticket types', function (): void {
        $event = Event::factory()->create();
        EventTicketType::factory()->create(['event_id' => $event->id, 'price' => 1500]);

        expect($event->effectivePricingMode())->toBe(PricingMode::Paid);
        expect($event->isFree())->toBeFalse();
    });

    it('derives mixed when both free and paid ticket types exist', function (): void {
        $event = Event::factory()->create();
        EventTicketType::factory()->freeTicket()->create(['event_id' => $event->id]);
        EventTicketType::factory()->create(['event_id' => $event->id, 'price' => 1500]);

        expect($event->effectivePricingMode())->toBe(PricingMode::Mixed);
    });

    it('assumes free when no ticket types exist', function (): void {
        $event = Event::factory()->create();

        expect($event->effectivePricingMode())->toBe(PricingMode::Free);
    });

    it('assumes paid when auto-derive is disabled', function (): void {
        config()->set('events.features.free_only.auto_derive_pricing_from_ticket_types', false);
        $event = Event::factory()->create();

        expect($event->effectivePricingMode())->toBe(PricingMode::Paid);
    });
});

describe('Event registration mode', function (): void {
    it('returns explicit override when set', function (): void {
        $event = Event::factory()->registrationMode(RegistrationMode::Optional)->create();

        expect($event->effectiveRegistrationMode())->toBe(RegistrationMode::Optional);
        expect($event->requiresRegistration())->toBeFalse();
        expect($event->isOpenDoor())->toBeFalse();
    });

    it('defaults to required', function (): void {
        $event = Event::factory()->create();

        expect($event->effectiveRegistrationMode())->toBe(RegistrationMode::Required);
        expect($event->requiresRegistration())->toBeTrue();
        expect($event->isOpenDoor())->toBeFalse();
    });

    it('identifies open door', function (): void {
        $event = Event::factory()->registrationMode(RegistrationMode::None)->create();

        expect($event->isOpenDoor())->toBeTrue();
        expect($event->requiresRegistration())->toBeFalse();
    });
});

describe('Event passes for free', function (): void {
    it('uses default from config', function (): void {
        $event = Event::factory()->create();

        expect($event->shouldIssuePassesForFree())->toBeTrue();
    });

    it('respects explicit override', function (): void {
        $event = Event::factory()->create(['issue_passes_for_free' => false]);

        expect($event->shouldIssuePassesForFree())->toBeFalse();
    });

    it('reads config default when not set', function (): void {
        config()->set('events.features.free_only.auto_issue_passes_for_free', false);
        $event = Event::factory()->create();

        expect($event->shouldIssuePassesForFree())->toBeFalse();
    });
});

describe('EventOccurrence inherits event modes', function (): void {
    it('inherits pricing mode from event', function (): void {
        $event = Event::factory()->free()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        expect($occurrence->effectivePricingMode())->toBe(PricingMode::Free);
        expect($occurrence->isFree())->toBeTrue();
    });

    it('derives pricing mode from occurrence ticket types', function (): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        EventTicketType::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'price' => 1500,
        ]);

        expect($occurrence->effectivePricingMode())->toBe(PricingMode::Paid);
        expect($occurrence->isFree())->toBeFalse();
    });

    it('overrides pricing mode at occurrence level', function (): void {
        $event = Event::factory()->paid()->create();
        $occurrence = EventOccurrence::factory()->free()->create(['event_id' => $event->id]);

        expect($occurrence->effectivePricingMode())->toBe(PricingMode::Free);
    });

    it('inherits pass issuance from event', function (): void {
        $event = Event::factory()->create(['issue_passes_for_free' => false]);
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        expect($occurrence->shouldIssuePassesForFree())->toBeFalse();
    });
});

describe('EventSession inherits modes', function (): void {
    it('inherits from occurrence and event', function (): void {
        $event = Event::factory()->free()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $session = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
        ]);

        expect($session->effectivePricingMode())->toBe(PricingMode::Free);
        expect($session->isFree())->toBeTrue();
    });

    it('derives pricing mode from session ticket types', function (): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $session = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
        ]);

        EventTicketType::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_session_id' => $session->id,
            'price' => 1500,
        ]);

        expect($session->effectivePricingMode())->toBe(PricingMode::Paid);
        expect($session->isFree())->toBeFalse();
    });
});

describe('Global scopes', function (): void {
    it('scopeFree filters free events', function (): void {
        Event::factory()->free()->create();
        Event::factory()->paid()->create();

        $freeEvents = Event::query()->free()->get();

        expect($freeEvents)->toHaveCount(1);
        expect($freeEvents->first()->isFree())->toBeTrue();
    });

    it('scopeOpenDoor filters open-door events', function (): void {
        Event::factory()->freeOpenDoor()->create();
        Event::factory()->free()->create();

        $openDoor = Event::query()->openDoor()->get();

        expect($openDoor)->toHaveCount(1);
        expect($openDoor->first()->isOpenDoor())->toBeTrue();
    });

    it('scopeWithResolvedModes eager loads ticket types', function (): void {
        Event::factory()->create();

        $event = Event::query()->withResolvedModes()->first();

        expect($event->relationLoaded('ticketTypes'))->toBeTrue();
    });
});
