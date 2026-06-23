<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Events\Contracts\EventPassDeliveryService;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventPass;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Notifications\EventTicketNotification;
use AIArmada\Events\Notifications\EventWelcomeNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
    config()->set('contacting.features.owner.enabled', false);
});

it('routes event registration mail notifications to the primary participant', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->id,
            'status' => 'pending',
        ]);

        $participant = $registration->participants()->create([
            'event_id' => $event->id,
            'name' => 'Alice Example',
            'is_primary' => true,
        ]);
        $participant->addContactMethod(ContactMethodData::email('alice@example.com'));

        $notification = new class extends Notification
        {
            /**
             * @return array<int, string>
             */
            public function via(object $notifiable): array
            {
                return ['mail'];
            }
        };

        expect($registration->routeNotificationForMail($notification))->toBe([
            'alice@example.com' => 'Alice Example',
        ]);
    });
});

it('sends a welcome notification when a registration is approved', function (): void {
    NotificationFacade::fake();
    config()->set('events.notifications.welcome.enabled', true);

    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->id,
            'status' => 'pending',
        ]);

        $participant = $registration->participants()->create([
            'event_id' => $event->id,
            'name' => 'Alice Example',
            'is_primary' => true,
        ]);
        $participant->addContactMethod(ContactMethodData::email('alice@example.com'));

        app(RegistrationServiceInterface::class)->approve($registration);

        NotificationFacade::assertSentOnDemand(
            EventWelcomeNotification::class,
            function (EventWelcomeNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
                return $channels === ['mail'] && ($notifiable->routes['mail'] ?? null) === [
                    'alice@example.com' => 'Alice Example',
                ];
            },
        );
    });
});

it('delivers ticket notifications through the default delivery service', function (): void {
    NotificationFacade::fake();
    config()->set('events.notifications.ticket.enabled', true);

    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'timezone' => 'UTC',
            'delivery_mode' => 'in_person',
        ]);
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'status' => 'pending',
        ]);

        $participant = $registration->participants()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'name' => 'Alice Example',
            'is_primary' => true,
        ]);
        $participant->addContactMethod(ContactMethodData::email('alice@example.com'));

        $pass = EventPass::query()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_registration_id' => $registration->id,
            'pass_no' => 'PASS-123456',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        app(EventPassDeliveryService::class)->deliver($pass);

        NotificationFacade::assertSentOnDemand(
            EventTicketNotification::class,
            function (EventTicketNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
                return $channels === ['mail'] && ($notifiable->routes['mail'] ?? null) === [
                    'alice@example.com' => 'Alice Example',
                ];
            },
        );
    });
});
