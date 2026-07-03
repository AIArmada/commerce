<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Notifications\EventWelcomeNotification;
use AIArmada\Ticketing\Contracts\PassDeliveryServiceInterface;
use AIArmada\Ticketing\Notifications\TicketNotification;
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

        $ticketType = createEventTicketType($occurrence);
        $pass = createEventPass($ticketType, $registration, [
            'pass_no' => 'PASS-123456',
        ]);
        $pass->holderHistory()->create([
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'is_current' => true,
        ]);

        app(PassDeliveryServiceInterface::class)->deliver($pass);

        NotificationFacade::assertSentOnDemand(
            TicketNotification::class,
            function (TicketNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
                return $channels === ['mail']
                    && ($notifiable->routes['mail'] ?? null) === 'alice@example.com';
            },
        );
    });
});

it('restores owner context while rendering serialized queued notifications', function (): void {
    config()->set('events.features.owner.enabled', true);
    $owner = User::factory()->create();

    [$welcome, $ticket] = OwnerContext::withOwner($owner, function (): array {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
        ]);
        $ticketType = createEventTicketType($occurrence);
        $pass = createEventPass($ticketType, $registration, [
            'pass_no' => 'PASS-QUEUED',
        ]);

        return [
            new EventWelcomeNotification($registration),
            new TicketNotification($pass),
        ];
    });

    $welcome = unserialize(serialize($welcome));
    $ticket = unserialize(serialize($ticket));

    expect($welcome->toMail(new AnonymousNotifiable)->subject)
        ->toContain('Registration Confirmed')
        ->and($ticket->toMail(new AnonymousNotifiable)->subject)
        ->toContain('Your Ticket');
});
