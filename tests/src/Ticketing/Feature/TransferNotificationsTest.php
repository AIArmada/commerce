<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Ticketing\Data\PassTransferData;
use AIArmada\Ticketing\Events\PassTransferred;
use AIArmada\Ticketing\Features\Passes\EntryPoints\Jobs\BulkSendTransferNotificationsJob;
use AIArmada\Ticketing\Features\Passes\EntryPoints\Listeners\SendTransferNotifications;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\Features\Passes\Models\PassHolder;
use AIArmada\Ticketing\Features\Passes\Queries\MapPassData;
use AIArmada\Ticketing\Features\Passes\Queries\MapPassHolderData;
use AIArmada\Ticketing\Notifications\PassTransferredToNewHolderNotification;
use AIArmada\Ticketing\Notifications\PassTransferredToOldHolderNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

function transferData(Pass $pass, PassHolder $previous, PassHolder $new, bool $bulk = true): PassTransferData
{
    return new PassTransferData(
        pass: app(MapPassData::class)->handle($pass),
        previousHolder: app(MapPassHolderData::class)->handle($previous),
        newHolder: app(MapPassHolderData::class)->handle($new),
        bulk: $bulk,
    );
}

it('attributes each bulk email to that pass previous holder', function (): void {
    Notification::fake();

    $passA = Pass::factory()->create();
    $passB = Pass::factory()->create();

    $previousA = PassHolder::factory()->create([
        'pass_id' => $passA->getKey(), 'name' => 'Previous A', 'email' => 'previous-a@example.com', 'is_current' => false,
    ]);
    $previousB = PassHolder::factory()->create([
        'pass_id' => $passB->getKey(), 'name' => 'Previous B', 'email' => 'previous-b@example.com', 'is_current' => false,
    ]);
    $currentA = PassHolder::factory()->create([
        'pass_id' => $passA->getKey(), 'name' => 'Current A', 'email' => 'current-a@example.com', 'is_current' => true,
    ]);
    $currentB = PassHolder::factory()->create([
        'pass_id' => $passB->getKey(), 'name' => 'Current B', 'email' => 'current-b@example.com', 'is_current' => true,
    ]);

    $owner = User::query()->whereKey($passA->owner_id)->firstOrFail();
    $transfers = [
        transferData($passA, $previousA, $currentA),
        transferData($passB, $previousB, $currentB),
    ];

    (new BulkSendTransferNotificationsJob(
        transfers: $transfers,
        ownerType: $owner->getMorphClass(),
        ownerId: $owner->getKey(),
    ))->handle();

    Notification::assertSentOnDemand(
        PassTransferredToNewHolderNotification::class,
        fn (PassTransferredToNewHolderNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'current-a@example.com'
            && $notification->previousHolder->id === (string) $previousA->getKey()
    );
    Notification::assertSentOnDemand(
        PassTransferredToNewHolderNotification::class,
        fn (PassTransferredToNewHolderNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'current-b@example.com'
            && $notification->previousHolder->id === (string) $previousB->getKey()
    );
    Notification::assertSentOnDemand(
        PassTransferredToOldHolderNotification::class,
        fn (PassTransferredToOldHolderNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'previous-a@example.com'
    );
});

it('loads bulk notification holders in one query', function (): void {
    Notification::fake();

    $passes = Pass::factory()->count(3)->create();
    $transfers = [];

    foreach ($passes as $pass) {
        $previous = PassHolder::factory()->create([
            'pass_id' => $pass->getKey(), 'is_current' => false,
        ]);
        $current = PassHolder::factory()->create([
            'pass_id' => $pass->getKey(), 'email' => "current-{$pass->getKey()}@example.com", 'is_current' => true,
        ]);
        $transfers[] = transferData($pass, $previous, $current);
    }

    $owner = User::query()->whereKey($passes->first()->owner_id)->firstOrFail();

    $job = new BulkSendTransferNotificationsJob(
        transfers: $transfers,
        ownerType: $owner->getMorphClass(),
        ownerId: $owner->getKey(),
    );

    DB::enableQueryLog();
    $job->handle();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $holderQueries = array_values(array_filter(
        $queries,
        fn (array $query): bool => str_contains($query['query'], 'pass_holders'),
    ));

    expect($holderQueries)->toHaveCount(1);
});

it('skips transfer emails when holder emails are blank', function (): void {
    Notification::fake();

    $pass = Pass::factory()->create();
    $previous = PassHolder::factory()->make(['email' => null]);
    $new = PassHolder::factory()->make(['email' => '']);

    (new SendTransferNotifications)->handle(new PassTransferred(transferData($pass, $previous, $new, bulk: false)));

    Notification::assertNothingSent();
});

it('sends both transfer emails when holder emails are present', function (): void {
    Notification::fake();

    $pass = Pass::factory()->create();
    $previous = PassHolder::factory()->create([
        'pass_id' => $pass->getKey(), 'email' => 'old-holder@example.com', 'is_current' => false,
    ]);
    $new = PassHolder::factory()->create([
        'pass_id' => $pass->getKey(), 'email' => 'new-holder-2@example.com', 'is_current' => true,
    ]);

    (new SendTransferNotifications)->handle(new PassTransferred(transferData($pass, $previous, $new, bulk: false)));

    Notification::assertSentOnDemand(
        PassTransferredToOldHolderNotification::class,
        fn (PassTransferredToOldHolderNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'old-holder@example.com'
    );
    Notification::assertSentOnDemand(
        PassTransferredToNewHolderNotification::class,
        fn (PassTransferredToNewHolderNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'new-holder-2@example.com'
    );
});

it('does not send a stale transfer notification to a subsequent holder', function (): void {
    Notification::fake();

    $pass = Pass::factory()->create();
    $recipient = PassHolder::factory()->create(['pass_id' => $pass->getKey(), 'is_current' => true]);
    $transfer = transferData($pass, $recipient, $recipient);

    $recipient->update(['is_current' => false]);
    PassHolder::factory()->create(['pass_id' => $pass->getKey(), 'is_current' => true]);

    $owner = User::query()->whereKey($pass->owner_id)->firstOrFail();

    (new BulkSendTransferNotificationsJob(
        transfers: [$transfer],
        ownerType: $owner->getMorphClass(),
        ownerId: $owner->getKey(),
    ))->handle();

    Notification::assertNothingSent();
});

it('round-trips the bulk notification job through serialization outside tenant context', function (): void {
    Notification::fake();

    $pass = Pass::factory()->create();
    $previous = PassHolder::factory()->create([
        'pass_id' => $pass->getKey(), 'email' => 'serialized-old@example.com', 'is_current' => false,
    ]);
    $current = PassHolder::factory()->create([
        'pass_id' => $pass->getKey(), 'email' => 'serialized-new@example.com', 'is_current' => true,
    ]);
    $owner = User::query()->whereKey($pass->owner_id)->firstOrFail();

    $job = new BulkSendTransferNotificationsJob(
        transfers: [transferData($pass, $previous, $current)],
        ownerType: $owner->getMorphClass(),
        ownerId: $owner->getKey(),
    );

    $serialized = OwnerContext::withOwner(null, fn (): string => serialize($job));
    $restored = unserialize($serialized);

    OwnerContext::withOwner(null, fn () => $restored->handle());

    Notification::assertSentOnDemand(
        PassTransferredToNewHolderNotification::class,
        fn (PassTransferredToNewHolderNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'serialized-new@example.com'
    );
});

it('does not duplicate bulk transfer notices through the configured single-transfer listener', function (): void {
    Notification::fake();

    $pass = Pass::factory()->create();
    $previous = PassHolder::factory()->create(['pass_id' => $pass->getKey(), 'is_current' => false, 'email' => 'bulk-old@example.com']);
    $current = PassHolder::factory()->create(['pass_id' => $pass->getKey(), 'is_current' => true, 'email' => 'bulk-new@example.com']);

    (new SendTransferNotifications)->handle(new PassTransferred(transferData($pass, $previous, $current, bulk: true)));

    Notification::assertNothingSent();
});
