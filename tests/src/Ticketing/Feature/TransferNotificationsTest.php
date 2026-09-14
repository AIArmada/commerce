<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Ticketing\Events\PassesBulkTransferred;
use AIArmada\Ticketing\Events\PassTransferred;
use AIArmada\Ticketing\Jobs\BulkSendTransferNotificationsJob;
use AIArmada\Ticketing\Listeners\SendTransferNotifications;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;
use AIArmada\Ticketing\Notifications\PassTransferredToNewHolderNotification;
use AIArmada\Ticketing\Notifications\PassTransferredToOldHolderNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

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
    PassHolder::factory()->create([
        'pass_id' => $passA->getKey(), 'name' => 'Current A', 'email' => 'current-a@example.com', 'is_current' => true,
    ]);
    PassHolder::factory()->create([
        'pass_id' => $passB->getKey(), 'name' => 'Current B', 'email' => 'current-b@example.com', 'is_current' => true,
    ]);

    $owner = User::query()->firstOrFail();
    $event = new PassesBulkTransferred(
        Pass::query()->whereIn('id', [$passA->getKey(), $passB->getKey()])->get(),
        previousHolders: PassHolder::query()
            ->whereIn('id', [$previousA->getKey(), $previousB->getKey()])
            ->get(),
    );

    (new BulkSendTransferNotificationsJob(
        event: $event,
        ownerType: $owner->getMorphClass(),
        ownerId: $owner->getKey(),
    ))->handle();

    Notification::assertSentOnDemand(
        PassTransferredToNewHolderNotification::class,
        fn (PassTransferredToNewHolderNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'current-a@example.com'
            && $notification->previousHolder->getKey() === $previousA->getKey()
    );
    Notification::assertSentOnDemand(
        PassTransferredToNewHolderNotification::class,
        fn (PassTransferredToNewHolderNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'current-b@example.com'
            && $notification->previousHolder->getKey() === $previousB->getKey()
    );
});

it('loads bulk notification holders in one query', function (): void {
    Notification::fake();

    $passes = Pass::factory()->count(3)->create();

    foreach ($passes as $pass) {
        PassHolder::factory()->create([
            'pass_id' => $pass->getKey(), 'email' => "current-{$pass->getKey()}@example.com", 'is_current' => true,
        ]);
    }

    $owner = User::query()->firstOrFail();
    $event = new PassesBulkTransferred(
        Pass::query()->whereIn('id', $passes->modelKeys())->get(),
    );

    $job = new BulkSendTransferNotificationsJob(
        event: $event,
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

    (new SendTransferNotifications)->handle(new PassTransferred($pass, $previous, $new));

    Notification::assertNothingSent();
});

it('sends both transfer emails when holder emails are present', function (): void {
    Notification::fake();

    $pass = Pass::factory()->create();
    $previous = PassHolder::factory()->make(['email' => 'old-holder@example.com']);
    $new = PassHolder::factory()->make(['email' => 'new-holder-2@example.com']);

    (new SendTransferNotifications)->handle(new PassTransferred($pass, $previous, $new));

    Notification::assertSentOnDemand(
        PassTransferredToOldHolderNotification::class,
        fn (PassTransferredToOldHolderNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'old-holder@example.com'
    );
    Notification::assertSentOnDemand(
        PassTransferredToNewHolderNotification::class,
        fn (PassTransferredToNewHolderNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'new-holder-2@example.com'
    );
});
