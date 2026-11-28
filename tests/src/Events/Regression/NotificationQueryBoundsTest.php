<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Communications\Contracts\CommunicationManager;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Events\Contracts\EventChangeNoticeAudienceResolver;
use AIArmada\Events\Data\EventChangeNoticeNotificationData;
use AIArmada\Events\Data\EventNotificationRecipientData;
use AIArmada\Events\Drivers\EventChangeNoticeNotificationDriver;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Changes\Queries\DefaultEventChangeNoticeAudienceResolver;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
    config()->set('contacting.features.owner.enabled', false);
    config()->set('communications.features.owner.enabled', false);
});

final class IntegerKeyNotificationRecipient extends Model
{
    public $incrementing = false;

    protected $table = 'users';

    protected $keyType = 'int';

    protected $guarded = [];

    public function routeNotificationForMail(?Notification $notification = null): string
    {
        return (string) $this->getAttribute('email');
    }
}

it('delivers to explicit and resolved audience recipients once each', function (): void {
    [$explicitUser, $audienceUser] = User::factory()->count(2)->create()->all();
    $explicit = new EventNotificationRecipientData($explicitUser->getMorphClass(), (string) $explicitUser->getKey());
    $audience = new EventNotificationRecipientData($audienceUser->getMorphClass(), (string) $audienceUser->getKey());

    $resolver = mock(EventChangeNoticeAudienceResolver::class);
    $resolver->shouldReceive('resolve')->once()->with('update-id', 'followers')->andReturn([$explicit, $audience]);

    $manager = mock(CommunicationManager::class);
    foreach ([$explicitUser, $audienceUser] as $user) {
        $manager->shouldReceive('notify')->once()
            ->withArgs(static fn (Model $notifiable): bool => $notifiable->is($user))
            ->andReturn('communication-id');
    }

    new EventChangeNoticeNotificationDriver($manager, $resolver)->dispatch(new EventChangeNoticeNotificationData(
        title: 'Event Change Notice',
        message: 'Combined audience',
        eventId: (string) Event::factory()->create()->getKey(),
        updateId: 'update-id',
        audienceScope: 'followers',
        recipients: [$explicit],
    ));
});

it('bulk-loads registration recipients without per-recipient query growth', function (): void {
    $event = Event::factory()->create();

    for ($index = 0; $index < 40; $index++) {
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->getKey(),
            'registration_no' => 'REG-BULK-' . $index,
            'status' => 'confirmed',
        ]);
        $participant = $registration->participants()->create([
            'event_id' => $event->getKey(),
            'name' => 'Recipient ' . $index,
            'is_primary' => true,
        ]);
        $participant->addContactMethod(ContactMethodData::email('recipient-' . $index . '@example.com'));
    }

    $manager = mock(CommunicationManager::class);
    $manager->shouldReceive('notify')->times(40)->andReturn('communication-id');
    app()->instance(CommunicationManager::class, $manager);

    $dispatcher = new EventChangeNoticeNotificationDriver(
        manager: $manager,
        audienceResolver: app(DefaultEventChangeNoticeAudienceResolver::class),
    );

    DB::flushQueryLog();
    DB::enableQueryLog();

    $dispatcher->dispatch(new EventChangeNoticeNotificationData(
        title: 'Event Change Notice',
        message: 'Bulk delivery',
        eventId: (string) $event->getKey(),
        audienceScope: 'registrants',
    ));

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThan(15);
});

it('bounds registration recipient memory by processing chunks without loading every notifiable at once', function (): void {
    $event = Event::factory()->create();

    for ($index = 0; $index < 520; $index++) {
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->getKey(),
            'registration_no' => 'REG-BATCH-' . $index,
            'status' => 'confirmed',
        ]);
        $participant = $registration->participants()->create([
            'event_id' => $event->getKey(),
            'name' => 'Batched Recipient ' . $index,
            'is_primary' => true,
        ]);
        $participant->addContactMethod(ContactMethodData::email('batched-' . $index . '@example.com'));
    }

    $manager = mock(CommunicationManager::class);
    $manager->shouldReceive('notify')->times(520)->andReturn('communication-id');
    app()->instance(CommunicationManager::class, $manager);

    $dispatcher = new EventChangeNoticeNotificationDriver(
        manager: $manager,
        audienceResolver: app(DefaultEventChangeNoticeAudienceResolver::class),
    );

    DB::flushQueryLog();
    DB::enableQueryLog();

    $dispatcher->dispatch(new EventChangeNoticeNotificationData(
        title: 'Event Change Notice',
        message: 'Batched delivery',
        eventId: (string) $event->getKey(),
        audienceScope: 'registrants',
    ));

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThan(25);
});

it('accepts valid integer notifiable keys', function (): void {
    DB::table('users')->insert([
        'id' => 900001,
        'name' => 'Integer Recipient',
        'email' => 'integer-recipient@example.com',
        'password' => 'secret',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $manager = mock(CommunicationManager::class);
    $manager->shouldReceive('notify')
        ->once()
        ->withArgs(static fn (mixed $notifiable): bool => $notifiable instanceof IntegerKeyNotificationRecipient
            && (string) $notifiable->getKey() === '900001')
        ->andReturn('communication-id');
    app()->instance(CommunicationManager::class, $manager);

    $dispatcher = new EventChangeNoticeNotificationDriver(
        manager: $manager,
        audienceResolver: app(DefaultEventChangeNoticeAudienceResolver::class),
    );

    $dispatcher->dispatch(new EventChangeNoticeNotificationData(
        title: 'Event Change Notice',
        message: 'Integer keys',
        eventId: (string) Event::factory()->create()->getKey(),
        audienceScope: 'followers',
        recipients: [
            new EventNotificationRecipientData(
                notifiableType: IntegerKeyNotificationRecipient::class,
                notifiableId: '900001',
            ),
        ],
    ));
});
