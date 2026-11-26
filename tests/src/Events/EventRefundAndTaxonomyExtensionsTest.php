<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Events\PaymentRefunded;
use AIArmada\Events\Contracts\EventRefundEligibility;
use AIArmada\Events\Contracts\EventTaxonomyHierarchy;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Commerce\EntryPoints\Listeners\CompletePendingRefundOnPaymentRefunded;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Features\Registrations\Models\EventRegistrationParticipant;
use AIArmada\Events\Features\Registrations\Queries\DefaultEventRefundEligibility;
use AIArmada\Events\Features\Registrations\States\RegistrationStatus\Confirmed;
use AIArmada\Events\Features\Taxonomy\Models\EventTaxonomy;
use AIArmada\Events\Features\Taxonomy\Models\EventTerm;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Str;

test('event registration routeNotificationForMail accepts nullable notification', function (): void {
    $event = Event::factory()->create();
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->getKey(),
    ]);
    $participant = EventRegistrationParticipant::query()->create([
        'event_registration_id' => $registration->getKey(),
        'event_id' => $event->getKey(),
        'name' => 'Ahmad',
        'is_primary' => true,
        'status' => 'registered',
    ]);
    $participant->addContactMethod([
        'type' => 'email',
        'value' => 'ahmad@example.com',
        'is_primary' => true,
    ]);

    expect($registration->routeNotificationForMail())->toBe(['ahmad@example.com' => 'Ahmad']);
});

test('event taxonomy hierarchy searchTerms returns filtered active terms', function (): void {
    $taxonomy = EventTaxonomy::query()->create([
        'code' => 'discipline-' . Str::random(4),
        'name' => 'Discipline',
        'is_active' => true,
    ]);

    EventTerm::query()->create([
        'event_taxonomy_id' => $taxonomy->getKey(),
        'code' => 'fiqh',
        'name' => 'Fiqh Muamalat',
        'slug' => 'fiqh-muamalat-' . Str::random(4),
        'is_active' => true,
        'sort_order' => 1,
    ]);

    EventTerm::query()->create([
        'event_taxonomy_id' => $taxonomy->getKey(),
        'code' => 'aqidah',
        'name' => 'Aqidah',
        'slug' => 'aqidah-' . Str::random(4),
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $hierarchy = app(EventTaxonomyHierarchy::class);
    $all = $hierarchy->searchTerms($taxonomy->code);
    $filtered = $hierarchy->searchTerms($taxonomy->code, 'fiqh');

    expect($all)->toHaveCount(2)
        ->and($filtered)->toHaveCount(1)
        ->and($filtered[0]['code'])->toBe('fiqh');
});

test('event taxonomy hierarchy reflects taxonomy and term edits in the same request', function (): void {
    $taxonomy = EventTaxonomy::factory()->create(['name' => 'Before']);
    $term = EventTerm::factory()->create(['event_taxonomy_id' => $taxonomy->id, 'is_active' => true]);
    $hierarchy = app(EventTaxonomyHierarchy::class);

    expect($hierarchy->taxonomy($taxonomy->code)?->name)->toBe('Before')
        ->and($hierarchy->terms($taxonomy->code))->toHaveCount(1);

    $taxonomy->update(['name' => 'After']);
    $term->delete();

    expect($hierarchy->taxonomy($taxonomy->code)?->name)->toBe('After')
        ->and($hierarchy->terms($taxonomy->code))->toBe([]);
});

test('event service provider binds EventRefundEligibility and PaymentRefunded listener', function (): void {
    expect(app(EventRefundEligibility::class))->toBeInstanceOf(DefaultEventRefundEligibility::class);

    $listeners = EventFacade::getRawListeners()[PaymentRefunded::class] ?? [];
    expect($listeners)->toContain(CompletePendingRefundOnPaymentRefunded::class);

    $event = Event::factory()->create(['metadata' => ['registration' => ['refunds_enabled' => false]]]);
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->getKey(),
        'status' => Confirmed::class,
        'total_amount' => 5000,
    ]);

    expect(app(EventRefundEligibility::class)->refundsEnabled((string) $event->getKey()))->toBeFalse()
        ->and(app(EventRefundEligibility::class)->canOrganizerRefund((string) $event->getKey(), (string) $registration->getKey()))->toBeFalse();
});
