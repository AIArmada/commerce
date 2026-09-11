<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Actions\DispatchManagedNotificationAction;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Enums\RecipientRole;
use AIArmada\Communications\Events\DeliverySending;
use AIArmada\Communications\Jobs\DispatchCommunicationDeliveriesJob;
use AIArmada\Communications\Jobs\DispatchManagedNotificationJob;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationRecipient;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

final class StreamAOrderedFirstChannel
{
    /** @var array<int, string> */
    public static array $calls = [];

    public function send(mixed $notifiable, mixed $notification): void
    {
        self::$calls[] = 'first';
    }
}

final class StreamAOrderedSecondChannel
{
    /** @var array<int, string> */
    public static array $calls = [];

    public function send(mixed $notifiable, mixed $notification): void
    {
        self::$calls[] = 'second';
    }
}

final class StreamAQueueNotifiable
{
    use Notifiable;

    public function getKey(): string
    {
        return 'stream-a-notifiable';
    }

    public function routeNotificationForMail(): string
    {
        return 'stream-a@example.com';
    }
}

final class StreamAOrderedNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return [
            StreamAOrderedFirstChannel::class,
            StreamAOrderedSecondChannel::class,
        ];
    }
}

test('managed notifications are queued with channels in notification order', function (): void {
    Queue::fake();

    $notifiable = new StreamAQueueNotifiable;
    $notification = new class extends Notification
    {
        public function via(object $notifiable): array
        {
            return ['mail'];
        }

        public function toMail(object $notifiable): MailMessage
        {
            return (new MailMessage)->subject('Queued notification')->line('Queued body');
        }
    };

    $communication = app(DispatchManagedNotificationAction::class)->handle(
        $notifiable,
        $notification,
        CommunicationContextData::from(['purpose' => 'queued-order-test']),
    );

    Queue::assertPushed(DispatchManagedNotificationJob::class, function (DispatchManagedNotificationJob $job) use ($communication): bool {
        return $job->channels === ['mail']
            && $job->ownerType === $communication->owner_type
            && $job->ownerId === $communication->owner_id
            && $job->afterCommit === true;
    });

    $deliveryStatuses = $communication->deliveries()
        ->get()
        ->map(fn (CommunicationDelivery $delivery): string => $delivery->status->value)
        ->all();

    expect($communication->status)->toBe(CommunicationStatus::Queued)
        ->and($deliveryStatuses)->toBe([DeliveryStatus::Queued->value]);
});

test('managed notification job sends channels in persisted order', function (): void {
    StreamAOrderedFirstChannel::$calls = [];
    StreamAOrderedSecondChannel::$calls = [];

    $job = new DispatchManagedNotificationJob(
        notifiable: new StreamAQueueNotifiable,
        notification: new StreamAOrderedNotification,
        channels: [
            StreamAOrderedFirstChannel::class,
            StreamAOrderedSecondChannel::class,
        ],
        ownerIsGlobal: true,
    );

    $job->handle();

    expect(StreamAOrderedFirstChannel::$calls)->toBe(['first'])
        ->and(StreamAOrderedSecondChannel::$calls)->toBe(['second']);
});

test('due communications queue eligible deliveries in creation order', function (): void {
    Queue::fake();

    $communication = Communication::create([
        'direction' => 'outbound',
        'category' => 'transactional',
        'priority' => 'normal',
        'purpose' => 'scheduled-order-test',
        'status' => CommunicationStatus::Scheduled,
        'scheduled_at' => now()->subMinute(),
    ]);
    $recipient = CommunicationRecipient::create([
        'communication_id' => $communication->id,
        'role' => RecipientRole::To,
    ]);
    $firstCreatedAt = now()->subMinutes(2);
    $secondCreatedAt = now()->subMinute();

    $first = CommunicationDelivery::create([
        'communication_id' => $communication->id,
        'recipient_id' => $recipient->id,
        'channel' => 'mail',
        'status' => DeliveryStatus::Pending,
        'created_at' => $firstCreatedAt,
        'updated_at' => $firstCreatedAt,
    ]);
    $second = CommunicationDelivery::create([
        'communication_id' => $communication->id,
        'recipient_id' => $recipient->id,
        'channel' => 'mail',
        'status' => DeliveryStatus::Pending,
        'created_at' => $secondCreatedAt,
        'updated_at' => $secondCreatedAt,
    ]);

    $owner = OwnerContext::resolve();

    $exitCode = Artisan::call('communications:dispatch-due', [
        '--owner' => $owner->getMorphClass() . ':' . $owner->getKey(),
    ]);

    Queue::assertPushed(DispatchCommunicationDeliveriesJob::class, fn (DispatchCommunicationDeliveriesJob $job): bool => $job->communicationId === $communication->id);

    expect($exitCode)->toBe(0)
        ->and($first->fresh()->status)->toBe(DeliveryStatus::Queued)
        ->and($second->fresh()->status)->toBe(DeliveryStatus::Queued)
        ->and($communication->fresh()->status)->toBe(CommunicationStatus::Queued);
});

test('delivery dispatch transitions queued deliveries in their queue order', function (): void {
    Event::fake([DeliverySending::class]);

    $communication = Communication::create([
        'direction' => 'outbound',
        'category' => 'transactional',
        'priority' => 'normal',
        'purpose' => 'delivery-order-test',
        'status' => CommunicationStatus::Queued,
    ]);
    $recipient = CommunicationRecipient::create([
        'communication_id' => $communication->id,
        'role' => RecipientRole::To,
    ]);
    $first = CommunicationDelivery::create([
        'communication_id' => $communication->id,
        'recipient_id' => $recipient->id,
        'channel' => 'mail',
        'status' => DeliveryStatus::Queued,
        'queued_at' => now()->subMinute(),
    ]);
    $second = CommunicationDelivery::create([
        'communication_id' => $communication->id,
        'recipient_id' => $recipient->id,
        'channel' => 'sms',
        'status' => DeliveryStatus::Queued,
        'queued_at' => now(),
    ]);

    (new DispatchCommunicationDeliveriesJob(
        communicationId: $communication->id,
        ownerType: $communication->owner_type,
        ownerId: $communication->owner_id,
    ))->handle();

    $deliveryIds = Event::dispatched(DeliverySending::class)
        ->map(fn (array $arguments): string => $arguments[0]->deliveryId)
        ->all();

    expect($deliveryIds)->toBe([$first->id, $second->id])
        ->and($first->fresh()->status)->toBe(DeliveryStatus::Sending)
        ->and($second->fresh()->status)->toBe(DeliveryStatus::Sending);
});
