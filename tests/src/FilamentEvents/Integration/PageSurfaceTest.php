<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventNotificationBatch;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventPass;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventTicketType;
use AIArmada\FilamentEvents\Pages\CheckInConsole;
use AIArmada\FilamentEvents\Pages\EventPublicPreview;
use AIArmada\FilamentEvents\Pages\NotificationCenter;
use AIArmada\FilamentEvents\Resources\EventResource\Pages\ViewEvent;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

afterEach(function (): void {
    if (class_exists(Mockery::class)) {
        Mockery::close();
    }
});

it('scopes the special page queries to the current owner', function (): void {
    $createGraph = function (User $owner, string $suffix): array {
        return OwnerContext::withOwner($owner, function () use ($suffix): array {
            $event = Event::factory()->create([
                'title' => 'Event ' . $suffix,
                'slug' => 'event-' . $suffix,
            ]);

            $occurrence = EventOccurrence::factory()->create([
                'event_id' => $event->id,
                'title' => 'Occurrence ' . $suffix,
                'slug' => 'occurrence-' . $suffix,
            ]);

            $session = EventSession::factory()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
                'title' => 'Session ' . $suffix,
                'slug' => 'session-' . $suffix,
            ]);

            $ticketType = EventTicketType::factory()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
                'event_session_id' => $session->id,
            ]);

            $registration = EventRegistration::factory()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
                'event_session_id' => $session->id,
            ]);

            $pass = EventPass::factory()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
                'event_session_id' => $session->id,
                'event_registration_id' => $registration->id,
                'event_ticket_type_id' => $ticketType->id,
            ]);

            $notificationBatch = EventNotificationBatch::factory()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
                'event_session_id' => $session->id,
            ]);

            return compact(
                'event',
                'occurrence',
                'session',
                'ticketType',
                'registration',
                'pass',
                'notificationBatch',
            );
        });
    };

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'filament-events-page-owner-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'filament-events-page-owner-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $ownerAGraph = $createGraph($ownerA, 'a');
    $ownerBGraph = $createGraph($ownerB, 'b');
    $makeTable = fn (): Table => Table::make(Mockery::mock(HasTable::class));

    $ownerAPasses = OwnerContext::withOwner($ownerA, fn (): array => (new CheckInConsole)->table($makeTable())->getQuery()->pluck('id')->all());
    $ownerBPasses = OwnerContext::withOwner($ownerB, fn (): array => (new CheckInConsole)->table($makeTable())->getQuery()->pluck('id')->all());
    $ownerANotifications = OwnerContext::withOwner($ownerA, fn (): array => (new NotificationCenter)->table($makeTable())->getQuery()->pluck('id')->all());
    $ownerBNotifications = OwnerContext::withOwner($ownerB, fn (): array => (new NotificationCenter)->table($makeTable())->getQuery()->pluck('id')->all());

    $previewPage = new EventPublicPreview;
    OwnerContext::withOwner($ownerA, function () use ($previewPage, $ownerBGraph): void {
        $previewPage->mount($ownerBGraph['event']->id);
    });

    expect($ownerAPasses)->toBe([$ownerAGraph['pass']->id])
        ->and($ownerBPasses)->toBe([$ownerBGraph['pass']->id])
        ->and($ownerANotifications)->toBe([$ownerAGraph['notificationBatch']->id])
        ->and($ownerBNotifications)->toBe([$ownerBGraph['notificationBatch']->id])
        ->and($previewPage->event)->toBeNull();
});

it('builds the special page header actions', function (): void {
    $pages = [
        new ViewEvent,
        new CheckInConsole,
        new NotificationCenter,
    ];

    foreach ($pages as $page) {
        $method = new ReflectionMethod($page, 'getHeaderActions');
        $method->setAccessible(true);

        expect($method->invoke($page))->toBeArray()->not->toBeEmpty();
    }
});
