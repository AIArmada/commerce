<?php

declare(strict_types=1);

use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Models\Event as EventModel;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use AIArmada\Events\Services\RegistrationService;
use AIArmada\Events\Support\CommerceIntegration;
use AIArmada\Orders\Models\Order;

it('detects first party order fulfillment support when commerce packages are installed', function (): void {
    expect(CommerceIntegration::aiArmadaOrderFulfillmentAvailable())->toBeTrue();
});

it('treats null integration models as unavailable for optional relationships', function (): void {
    config()->set('events.integrations.product_model', null);
    config()->set('events.integrations.order_model', null);

    expect(CommerceIntegration::modelClass('product_model'))->toBeNull()
        ->and(CommerceIntegration::modelClass('order_model'))->toBeNull();

    expect(fn (): mixed => (new EventModel)->product())
        ->toThrow(RuntimeException::class, 'events products integration is unavailable');

    expect(fn (): mixed => (new Registration)->order())
        ->toThrow(RuntimeException::class, 'events orders integration is unavailable');
});

it('keeps direct occurrence registrations working without commerce integration models configured', function (): void {
    config()->set('events.integrations.product_model', null);
    config()->set('events.integrations.variant_model', null);
    config()->set('events.integrations.customer_model', null);
    config()->set('events.integrations.order_model', null);
    config()->set('events.integrations.order_item_model', null);

    $event = EventModel::query()->create([
        'name' => 'Core Only Event',
        'slug' => 'core-only-event',
        'status' => EventStatus::Active,
        'default_timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $occurrence = Occurrence::query()->create([
        'event_id' => $event->id,
        'status' => OccurrenceStatus::Scheduled,
        'starts_at' => now()->addWeek(),
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $registration = app(RegistrationService::class)->createForOccurrence($occurrence, [
        'name' => 'Core Guest',
        'email' => 'core-guest@example.com',
    ]);

    expect($registration)->toBeInstanceOf(Registration::class)
        ->and($registration->occurrence_id)->toBe($occurrence->id)
        ->and($registration->email)->toBe('core-guest@example.com')
        ->and($registration->order_id)->toBeNull();
});

it('resolves configured commerce model classes when they are available', function (): void {
    config()->set('events.integrations.order_model', Order::class);

    expect(CommerceIntegration::modelClass('order_model'))->toBe(Order::class);
});
