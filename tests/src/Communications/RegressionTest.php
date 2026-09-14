<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Actions\AddCommunicationRecipientAction;
use AIArmada\Communications\Actions\ApplyProviderEventAction;
use AIArmada\Communications\Actions\CreateTrackingTokenAction;
use AIArmada\Communications\Actions\DispatchManagedNotificationAction;
use AIArmada\Communications\Actions\PlanCommunicationDeliveriesAction;
use AIArmada\Communications\Actions\ReceiveInboundCommunicationAction;
use AIArmada\Communications\Actions\RecordTrackingInteractionAction;
use AIArmada\Communications\Actions\ResolveCommunicationThreadAction;
use AIArmada\Communications\Actions\StartDeliveryAttemptAction;
use AIArmada\Communications\Contracts\CommunicationRecorder;
use AIArmada\Communications\Contracts\DestinationProtector;
use AIArmada\Communications\Contracts\IdempotencyLock;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Data\CreatedTrackingTokenData;
use AIArmada\Communications\Data\PlannedDeliveryData;
use AIArmada\Communications\Data\ProviderEventData;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Enums\NotificationFamily;
use AIArmada\Communications\Enums\NotificationPriority;
use AIArmada\Communications\Enums\NotificationTrigger;
use AIArmada\Communications\Enums\RecipientRole;
use AIArmada\Communications\Enums\TrackingInteractionType;
use AIArmada\Communications\Events\CommunicationExpired;
use AIArmada\Communications\Events\DeliveryDelivered;
use AIArmada\Communications\Jobs\ProcessWebhookEventJob;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationAttachment;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationEvent;
use AIArmada\Communications\Models\CommunicationRecipient;
use AIArmada\Communications\Models\CommunicationThread;
use AIArmada\Communications\Policies\CommunicationPolicy;
use AIArmada\Communications\Services\CommunicationManagerService;
use AIArmada\Communications\Services\NotificationInboxService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

final class RepairQueueNotifiable
{
    use Notifiable;

    public function getKey(): string
    {
        return 'repair-notifiable-1';
    }

    public function routeNotificationForMail(): string
    {
        return 'repair@example.com';
    }

    public function routeNotificationForSms(): string
    {
        return '+10000000001';
    }
}

final class RepairMorphUser extends User
{
    public function getMorphClass(): string
    {
        return 'repair-morph-user';
    }

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }
}

final class RepairEmptyNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return [];
    }
}

final class RepairMailNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Repair mail')->line('Repair body');
    }
}

function regressionCommunication(array $attributes = []): Communication
{
    $communication = (new Communication)->forceFill(array_merge([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'repair-regression',
        'status' => CommunicationStatus::Draft,
    ], $attributes));
    $communication->save();

    return $communication;
}

function regressionDelivery(Communication $communication, array $attributes = []): CommunicationDelivery
{
    $recipient = CommunicationRecipient::create([
        'communication_id' => $communication->id,
        'role' => RecipientRole::To,
    ]);

    $delivery = (new CommunicationDelivery)->forceFill(array_merge([
        'communication_id' => $communication->id,
        'recipient_id' => $recipient->id,
        'channel' => 'mail',
        'provider' => 'array',
        'status' => DeliveryStatus::Pending,
        'attempt_count' => 0,
        'max_attempts' => 3,
    ], $attributes));
    $delivery->save();

    return $delivery;
}

function regressionSignedWebhookPost(object $testCase, array $payload, array $options = [])
{
    $secret = $options['secret'] ?? 'test-secret';
    $provider = $options['provider'] ?? 'sendgrid';
    $algorithm = $options['algorithm'] ?? 'sha256';
    $header = $options['header'] ?? 'HTTP_X_WEBHOOK_SIGNATURE';
    $timestamp = $options['timestamp'] ?? CarbonImmutable::now()->timestamp;
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = hash_hmac($algorithm, $body, $secret);

    return $testCase->call(
        'POST',
        'communications/webhooks/' . $provider,
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            $header => $signature,
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $timestamp,
        ],
        $body,
    );
}

beforeEach(function (): void {
    Config::set('communications.webhooks.providers.sendgrid.secret', 'test-secret');
});

test('tracking token creation returns the plaintext token', function (): void {
    $communication = regressionCommunication();
    $delivery = regressionDelivery($communication);

    $created = app(CreateTrackingTokenAction::class)->handle(
        deliveryId: $delivery->id,
        kind: 'click',
    );

    expect($created)->toBeInstanceOf(CreatedTrackingTokenData::class);
    expect(hash('sha256', $created->plaintextToken))->toBe($created->trackingToken->token_hash);
    expect($created->trackingToken->target_url_ciphertext)->toBeNull();
    expect(method_exists(CreateTrackingTokenAction::class, 'getToken'))->toBeFalse();
});

test('tracking target urls are validated and encrypted at rest', function (): void {
    $communication = regressionCommunication();
    $delivery = regressionDelivery($communication);
    $action = app(CreateTrackingTokenAction::class);

    expect(fn () => $action->handle($delivery->id, 'click', 'javascript:alert(1)'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $action->handle($delivery->id, 'click', 'not-a-url'))
        ->toThrow(InvalidArgumentException::class);

    $created = $action->handle($delivery->id, 'click', 'https://example.com/welcome?x=1');

    expect($created->trackingToken->target_host)->toBe('example.com');
    expect($created->trackingToken->target_url_ciphertext)->not->toBe('https://example.com/welcome?x=1');
    expect(app(DestinationProtector::class)->decrypt($created->trackingToken->target_url_ciphertext))
        ->toBe('https://example.com/welcome?x=1');
});

test('tracking target hosts can be allowlisted', function (): void {
    Config::set('communications.tracking.allowed_hosts', ['example.com']);

    $communication = regressionCommunication();
    $delivery = regressionDelivery($communication);
    $action = app(CreateTrackingTokenAction::class);

    expect(fn () => $action->handle($delivery->id, 'click', 'https://evil.example/welcome'))
        ->toThrow(InvalidArgumentException::class);

    $created = $action->handle($delivery->id, 'click', 'https://example.com/ok');

    expect($created->trackingToken->target_host)->toBe('example.com');
});

test('tampered destination ciphertext fails closed', function (): void {
    $protector = app(DestinationProtector::class);
    $ciphertext = $protector->encrypt('victim@example.com');

    expect($protector->decrypt($ciphertext))->toBe('victim@example.com');

    $tampered = mb_substr($ciphertext, 0, -2) . 'xx';

    expect(fn () => $protector->decrypt($tampered))->toThrow(DecryptException::class);
    expect(fn () => $protector->decrypt('not-ciphertext'))->toThrow(DecryptException::class);
});

test('recorder completes or fails the communication from delivery statuses', function (): void {
    $recorder = app(CommunicationRecorder::class);

    $completed = regressionCommunication();
    $completedDelivery = regressionDelivery($completed);
    $recorder->markSent($completed->id, $completedDelivery->id, []);

    expect($completed->fresh()->status)->toBe(CommunicationStatus::Completed);
    expect($completed->fresh()->completed_at)->not->toBeNull();

    $failed = regressionCommunication();
    $failedDelivery = regressionDelivery($failed);
    $recorder->markFailed($failed->id, $failedDelivery->id, 'bounce');

    expect($failed->fresh()->status)->toBe(CommunicationStatus::Failed);
    expect($failed->fresh()->failed_at)->not->toBeNull();

    $partial = regressionCommunication();
    $sentDelivery = regressionDelivery($partial);
    $failedDelivery = regressionDelivery($partial);
    $recorder->markSent($partial->id, $sentDelivery->id, []);
    $recorder->markFailed($partial->id, $failedDelivery->id, 'bounce');

    expect($partial->fresh()->status)->toBe(CommunicationStatus::PartiallyCompleted);
});

test('provider events transition through the state machine and emit events', function (): void {
    Event::fake([DeliveryDelivered::class]);

    $communication = regressionCommunication();
    $delivery = regressionDelivery($communication);

    app(ApplyProviderEventAction::class)->handle(new ProviderEventData(
        provider: 'sendgrid',
        providerEventId: 'repair-delivered-1',
        providerMessageId: null,
        eventType: 'delivery',
        occurredAt: CarbonImmutable::now(),
        communicationId: $communication->id,
        deliveryId: $delivery->id,
    ));

    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Delivered);
    expect($delivery->fresh()->delivered_at)->not->toBeNull();

    Event::assertDispatched(DeliveryDelivered::class, fn (DeliveryDelivered $event): bool => $event->deliveryId === $delivery->id);
});

test('late provider events never regress terminal deliveries', function (): void {
    $communication = regressionCommunication();
    $delivery = regressionDelivery($communication, [
        'status' => DeliveryStatus::Failed,
        'failed_at' => CarbonImmutable::now(),
    ]);

    app(ApplyProviderEventAction::class)->handle(new ProviderEventData(
        provider: 'sendgrid',
        providerEventId: 'repair-late-open-1',
        providerMessageId: null,
        eventType: 'open',
        occurredAt: CarbonImmutable::now(),
        communicationId: $communication->id,
        deliveryId: $delivery->id,
    ));

    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Failed);
    expect(CommunicationEvent::query()->where('provider_event_id', 'repair-late-open-1')->count())->toBe(1);
});

test('replayed webhook events move through the state machine', function (): void {
    $communication = regressionCommunication();
    $delivery = regressionDelivery($communication);

    CommunicationEvent::create([
        'communication_id' => $communication->id,
        'delivery_id' => $delivery->id,
        'source' => 'provider',
        'event' => 'open',
        'provider' => 'sendgrid',
        'provider_event_id' => 'repair-replay-1',
        'occurred_at' => CarbonImmutable::now(),
        'received_at' => CarbonImmutable::now(),
    ]);

    $owner = OwnerContext::resolve();
    $exitCode = Artisan::call('communications:replay-webhooks', [
        '--owner' => $owner->getMorphClass() . ':' . $owner->getKey(),
    ]);

    expect($exitCode)->toBe(0);
    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Opened);
    expect($delivery->fresh()->opened_at)->not->toBeNull();
});

test('provider events derive the delivery owner when none is resolved', function (): void {
    $owner = User::create([
        'name' => 'Repair Owner',
        'email' => 'repair-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $delivery = OwnerContext::withOwner($owner, function (): CommunicationDelivery {
        return regressionDelivery(regressionCommunication());
    });

    OwnerContext::withOwner(null, function () use ($delivery): void {
        app(ApplyProviderEventAction::class)->handle(new ProviderEventData(
            provider: 'sendgrid',
            providerEventId: 'repair-null-owner-1',
            providerMessageId: null,
            eventType: 'delivery',
            occurredAt: CarbonImmutable::now(),
            deliveryId: $delivery->id,
        ));
    });

    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Delivered);
});

test('duplicate idempotency keys are rejected atomically', function (): void {
    $manager = app(CommunicationManagerService::class);
    $context = CommunicationContextData::from([
        'purpose' => 'repair-idempotent',
        'idempotencyKey' => 'repair-idempotency-1',
    ]);

    $manager->notify(new RepairQueueNotifiable, new RepairEmptyNotification, $context);

    expect(fn () => $manager->notify(new RepairQueueNotifiable, new RepairEmptyNotification, $context))
        ->toThrow(RuntimeException::class, 'Duplicate communication detected');
});

test('failed dispatches release the idempotency lock', function (): void {
    $manager = app(CommunicationManagerService::class);
    $key = 'repair-idempotency-release-1';
    $context = CommunicationContextData::from([
        'purpose' => 'repair-idempotent-release',
        'idempotencyKey' => $key,
    ]);

    expect(fn () => $manager->notify('not-a-notifiable', new RepairEmptyNotification, $context))
        ->toThrow(InvalidArgumentException::class);

    expect(app(IdempotencyLock::class)->acquire($key, 60))->toBeTrue();
});

test('planning caps batch size and validates dates', function (): void {
    $communication = regressionCommunication();
    $recipient = CommunicationRecipient::create([
        'communication_id' => $communication->id,
        'role' => RecipientRole::To,
    ]);

    $makePlan = fn (): PlannedDeliveryData => new PlannedDeliveryData(
        recipientId: $recipient->id,
        channel: 'mail',
        destinationHash: hash('sha256', 'repair@example.com'),
        destinationHint: 'r***@example.com',
        destinationCiphertext: 'encrypted',
    );

    $tooMany = [];

    for ($i = 0; $i < 501; $i++) {
        $tooMany[] = $makePlan();
    }

    expect(fn () => app(PlanCommunicationDeliveriesAction::class)->handle($communication->id, $tooMany))
        ->toThrow(InvalidArgumentException::class, 'maximum is 500');

    $badDate = new PlannedDeliveryData(
        recipientId: $recipient->id,
        channel: 'mail',
        destinationHash: hash('sha256', 'repair@example.com'),
        destinationHint: 'r***@example.com',
        destinationCiphertext: 'encrypted',
        scheduledAt: 'not-a-date',
    );

    expect(fn () => app(PlanCommunicationDeliveriesAction::class)->handle($communication->id, [$badDate]))
        ->toThrow(InvalidArgumentException::class, 'Invalid planned delivery scheduled date');

    $deliveries = app(PlanCommunicationDeliveriesAction::class)->handle($communication->id, [$makePlan()]);

    expect($deliveries)->toHaveCount(1);
});

test('deleting a communication detaches children instead of orphaning them', function (): void {
    $parent = regressionCommunication();
    $child = regressionCommunication(['parent_id' => $parent->id]);

    $parent->delete();

    expect(Communication::query()->find($child->id))->not->toBeNull();
    expect($child->fresh()->parent_id)->toBeNull();
});

test('oversized webhook payloads are rejected', function (): void {
    $response = regressionSignedWebhookPost($this, ['blob' => str_repeat('x', 300000)]);

    $response->assertStatus(413);
});

test('deeply nested webhook payloads are rejected', function (): void {
    $payload = ['leaf' => true];

    for ($i = 0; $i < 40; $i++) {
        $payload = ['nested' => $payload];
    }

    $response = regressionSignedWebhookPost($this, $payload);

    $response->assertStatus(413);
});

test('expired and revoked tokens reject interactions', function (): void {
    $communication = regressionCommunication();
    $delivery = regressionDelivery($communication);
    $action = app(RecordTrackingInteractionAction::class);

    $created = app(CreateTrackingTokenAction::class)->handle($delivery->id, 'click');

    expect(fn () => $action->handle($created->trackingToken->id, 'teleport'))
        ->toThrow(InvalidArgumentException::class, 'Unknown tracking interaction type');

    $created->trackingToken->update(['expires_at' => CarbonImmutable::now()->subMinute()]);

    expect(fn () => $action->handle($created->trackingToken->id, TrackingInteractionType::Click))
        ->toThrow(RuntimeException::class, 'has expired');

    $created->trackingToken->update([
        'expires_at' => null,
        'revoked_at' => CarbonImmutable::now(),
    ]);

    expect(fn () => $action->handle($created->trackingToken->id, 'click'))
        ->toThrow(RuntimeException::class, 'has been revoked');

    expect(CommunicationEvent::query()->count())->toBe(0);
});

test('managed notifications validate notifiables and channels', function (): void {
    $action = app(DispatchManagedNotificationAction::class);
    $context = CommunicationContextData::from(['purpose' => 'repair-notifiable']);

    expect(fn () => $action->handle('scalar-notifiable', new RepairMailNotification, $context))
        ->toThrow(InvalidArgumentException::class, 'Notifiable must be an object');

    $badChannel = new class extends Notification
    {
        public function via(object $notifiable): array
        {
            return [''];
        }
    };

    expect(fn () => $action->handle(new RepairQueueNotifiable, $badChannel, $context))
        ->toThrow(InvalidArgumentException::class, 'non-empty strings');
});

test('model notifiables use their morph class and configured attempts', function (): void {
    Queue::fake();

    Config::set('communications.defaults.max_attempts', 7);

    $user = RepairMorphUser::create([
        'name' => 'Repair Morph',
        'email' => 'repair-morph-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $communication = app(DispatchManagedNotificationAction::class)->handle(
        $user,
        new RepairMailNotification,
        CommunicationContextData::from(['purpose' => 'repair-morph']),
    );

    $recipient = $communication->recipients()->sole();

    expect($recipient->recipient_type)->toBe('repair-morph-user');
    expect($communication->deliveries()->sole()->max_attempts)->toBe(7);
});

test('inbound content redacts credentials and validates sender morphs', function (): void {
    $action = app(ReceiveInboundCommunicationAction::class);

    expect(fn () => $action->handle(
        fromType: 'NonExistentSenderModel',
        fromId: 'sender-1',
        channel: 'mail',
    ))->toThrow(InvalidArgumentException::class, 'does not resolve to a model');

    $communication = $action->handle(
        fromType: User::class,
        fromId: 'sender-1',
        channel: 'mail',
        body: 'please use Bearer secret-token-xyz for access',
        subject: 'support request',
    );

    $content = $communication->contents()->sole();

    expect($content->content_text)->toBe('please use Bearer **[REDACTED]** for access');
    expect($content->subject)->toBe('support request');
});

test('webhook signatures support per-provider algorithms and headers', function (): void {
    Queue::fake();

    Config::set('communications.webhooks.providers.sendgrid.algorithm', 'sha512');
    Config::set('communications.webhooks.providers.sendgrid.signature_header', 'X-Repair-Signature');

    $response = regressionSignedWebhookPost(
        $this,
        ['event' => 'delivery.delivered'],
        ['algorithm' => 'sha512', 'header' => 'HTTP_X_REPAIR_SIGNATURE'],
    );

    $response->assertStatus(202);

    $rejected = regressionSignedWebhookPost($this, ['event' => 'delivery.delivered']);

    $rejected->assertStatus(401);
});

test('webhook fingerprints prefer provider event ids', function (): void {
    $first = new ProcessWebhookEventJob('sendgrid', ['id' => 'evt-same', 'n' => 1]);
    $second = new ProcessWebhookEventJob('sendgrid', ['id' => 'evt-same', 'n' => 2]);
    $third = new ProcessWebhookEventJob('sendgrid', ['id' => 'evt-other', 'n' => 1]);

    expect($first->uniqueId())->toBe($second->uniqueId());
    expect($first->uniqueId())->not->toBe($third->uniqueId());

    $unencodable = new ProcessWebhookEventJob('sendgrid', ['handle' => tmpfile()]);

    expect(fn () => $unencodable->uniqueId())->toThrow(JsonException::class);
});

test('expiring preserves deadlines and expires pending deliveries', function (): void {
    Event::fake([CommunicationExpired::class]);

    $deadline = CarbonImmutable::now()->subDay();
    $communication = regressionCommunication([
        'status' => CommunicationStatus::Scheduled,
        'scheduled_at' => CarbonImmutable::now()->subDays(2),
        'expires_at' => $deadline,
    ]);
    $delivery = regressionDelivery($communication);
    $sentDelivery = regressionDelivery($communication, ['status' => DeliveryStatus::Sent]);

    $owner = OwnerContext::resolve();
    $exitCode = Artisan::call('communications:expire', [
        '--owner' => $owner->getMorphClass() . ':' . $owner->getKey(),
    ]);

    expect($exitCode)->toBe(0);
    expect($communication->fresh()->status)->toBe(CommunicationStatus::Expired);
    expect($communication->fresh()->expires_at->toIso8601String())->toBe($deadline->toIso8601String());
    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Expired);
    expect($delivery->fresh()->expired_at)->not->toBeNull();
    expect($sentDelivery->fresh()->status)->toBe(DeliveryStatus::Expired);

    Event::assertDispatched(CommunicationExpired::class);
});

test('invalid prune dates fail with a validation error', function (): void {
    expect(Artisan::call('communications:prune', ['--before' => 'not-a-date']))->toBe(1);
    expect(Artisan::output())->toContain('Invalid --before date');

    expect(Artisan::call('communications:prune-inboxes', ['--before' => 'not-a-date']))->toBe(1);
    expect(Artisan::output())->toContain('Invalid --before date');
});

test('concurrent thread resolution returns a single thread', function (): void {
    $action = app(ResolveCommunicationThreadAction::class);

    $first = $action->handle(channel: 'mail', externalThreadId: 'thread-repair-1');
    $second = $action->handle(channel: 'mail', externalThreadId: 'thread-repair-1');

    expect($second->id)->toBe($first->id);
    expect(CommunicationThread::query()->where('external_thread_id', 'thread-repair-1')->count())->toBe(1);
});

test('delivery attempts number sequentially per delivery', function (): void {
    $communication = regressionCommunication();
    $delivery = regressionDelivery($communication);
    $action = app(StartDeliveryAttemptAction::class);

    $first = $action->handle($delivery->id);
    $second = $action->handle($delivery->id);

    expect($first->attempt_number)->toBe(1);
    expect($second->attempt_number)->toBe(2);
    expect($delivery->fresh()->attempt_count)->toBe(2);
});

test('inbox service rejects cross-owner communications', function (): void {
    $otherOwner = User::create([
        'name' => 'Repair Other Owner',
        'email' => 'repair-other-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $foreign = OwnerContext::withOwner($otherOwner, fn (): Communication => regressionCommunication());

    $user = User::create([
        'name' => 'Repair Recipient',
        'email' => 'repair-recipient-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    expect(fn () => app(NotificationInboxService::class)->create(
        recipient: $user,
        communication: $foreign,
        family: NotificationFamily::EventReminder,
        priority: NotificationPriority::Normal,
        trigger: NotificationTrigger::EventPublished,
        title: 'Cross owner inbox',
    ))->toThrow(AuthorizationException::class);
});

test('communication policy explicitly denies generic edits', function (): void {
    $policy = app(CommunicationPolicy::class);
    $owner = OwnerContext::resolve();
    $communication = regressionCommunication();

    expect($policy->update($owner, $communication))->toBeFalse();
    expect($policy->delete($owner, $communication))->toBeFalse();
    expect($policy->cancel($owner, $communication))->toBeTrue();
});

test('auto-capture marks every channel sent without state collisions', function (): void {
    Config::set('communications.features.auto_capture', true);

    $notifiable = new RepairQueueNotifiable;

    $notification = new class extends Notification
    {
        public function via(object $notifiable): array
        {
            return ['mail', 'sms'];
        }

        public function toMail(object $notifiable): MailMessage
        {
            return (new MailMessage)->subject('Repair')->line('Repair body');
        }
    };

    Config::set('communications.features.auto_capture_allowlist', [$notification::class]);

    event(new NotificationSending($notifiable, $notification, 'mail'));
    event(new NotificationSending($notifiable, $notification, 'sms'));

    event(new NotificationSent($notifiable, $notification, 'mail', 'msg-mail'));
    event(new NotificationSent($notifiable, $notification, 'sms', 'msg-sms'));

    $statuses = CommunicationDelivery::query()->pluck('status')->all();

    expect($statuses)->toHaveCount(2);

    foreach ($statuses as $status) {
        expect($status instanceof DeliveryStatus ? $status->value : (string) $status)->toBe('sent');
    }
});

test('recipient creation cannot reach another owner communication', function (): void {
    $otherOwner = User::create([
        'name' => 'Repair Foreign Owner',
        'email' => 'repair-foreign-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $foreign = OwnerContext::withOwner($otherOwner, fn (): Communication => regressionCommunication());

    expect(fn () => app(AddCommunicationRecipientAction::class)->handle(
        communicationId: $foreign->id,
        recipientType: User::class,
        recipientId: 'user-1',
        role: RecipientRole::To,
    ))->toThrow(ModelNotFoundException::class);
});

test('attachment storage fields are validated server-side', function (): void {
    $communication = regressionCommunication();

    $make = fn (array $attributes): CommunicationAttachment => new CommunicationAttachment(array_merge([
        'communication_id' => $communication->id,
        'filename' => 'invoice.pdf',
    ], $attributes));

    expect(fn () => $make(['storage_path' => '../etc/passwd'])->save())
        ->toThrow(InvalidArgumentException::class, 'without traversal');
    expect(fn () => $make(['storage_path' => '/etc/passwd'])->save())
        ->toThrow(InvalidArgumentException::class, 'without traversal');
    expect(fn () => $make(['mime_type' => 'not-a-mime'])->save())
        ->toThrow(InvalidArgumentException::class, 'type/subtype');
    expect(fn () => $make(['size_bytes' => -5])->save())
        ->toThrow(InvalidArgumentException::class, 'between 0 and');
    expect(fn () => $make(['size_bytes' => 10485761])->save())
        ->toThrow(InvalidArgumentException::class, 'between 0 and');

    Config::set('communications.attachments.allowed_disks', ['local']);

    expect(fn () => $make(['storage_disk' => 's3', 'storage_path' => 'a/b.pdf'])->save())
        ->toThrow(InvalidArgumentException::class, 'not allowed');

    Config::set('communications.attachments.allowed_mimes', ['application/pdf']);

    expect(fn () => $make(['mime_type' => 'application/x-sh'])->save())
        ->toThrow(InvalidArgumentException::class, 'not allowed');

    $attachment = $make([
        'storage_disk' => 'local',
        'storage_path' => 'attachments/invoice.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024,
    ]);
    $attachment->save();

    expect($attachment->exists)->toBeTrue();
});
