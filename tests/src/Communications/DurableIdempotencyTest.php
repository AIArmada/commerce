<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Actions\AttachCommunicationReferenceAction;
use AIArmada\Communications\Actions\DispatchManagedNotificationAction;
use AIArmada\Communications\Contracts\CommunicationContextAware;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Jobs\DispatchManagedNotificationJob;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationContent;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationRecipient;
use AIArmada\Communications\Models\CommunicationReference;
use AIArmada\Communications\Services\CommunicationManagerService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

final class DurableIdempotencyNotifiable
{
    use Notifiable;

    public function getKey(): string
    {
        return 'durable-idempotency-notifiable';
    }

    public function routeNotificationForMail(): string
    {
        return 'durable-idempotency@example.com';
    }
}

final class DurableIdempotencyMailNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Durable')->line('Durable');
    }
}

it('returns the winning communication and skips duplicate side effects', function (): void {
    Queue::fake();

    $action = app(DispatchManagedNotificationAction::class);
    $context = CommunicationContextData::from([
        'purpose' => 'durable-idempotency',
        'idempotencyKey' => ' durable-key-1 ',
    ]);

    $first = $action->handle(new DurableIdempotencyNotifiable, new DurableIdempotencyMailNotification, $context);
    $second = $action->handle(new DurableIdempotencyNotifiable, new DurableIdempotencyMailNotification, $context);

    expect($second->id)->toBe($first->id)
        ->and($first->idempotency_key)->toBe('durable-key-1')
        ->and(Communication::query()->where('idempotency_key', 'durable-key-1')->count())->toBe(1)
        ->and(CommunicationRecipient::query()->where('communication_id', $first->id)->count())->toBe(1)
        ->and(CommunicationContent::query()->where('communication_id', $first->id)->count())->toBe(1)
        ->and(CommunicationDelivery::query()->where('communication_id', $first->id)->count())->toBe(1);

    Queue::assertPushed(DispatchManagedNotificationJob::class, 1);
});

it('rejects duplicates through the strict unique dispatch path', function (): void {
    Queue::fake();

    $action = app(DispatchManagedNotificationAction::class);
    $context = CommunicationContextData::from([
        'purpose' => 'durable-idempotency-strict',
        'idempotencyKey' => 'durable-key-2',
    ]);

    $action->handleUnique(new DurableIdempotencyNotifiable, new DurableIdempotencyMailNotification, $context);

    expect(fn (): Communication => $action->handleUnique(new DurableIdempotencyNotifiable, new DurableIdempotencyMailNotification, $context))
        ->toThrow(RuntimeException::class, 'Duplicate communication detected for idempotency key: durable-key-2');
});

it('rejects blank idempotency keys before writing', function (): void {
    $action = app(DispatchManagedNotificationAction::class);
    $context = CommunicationContextData::from([
        'purpose' => 'durable-idempotency-blank',
        'idempotencyKey' => " \t ",
    ]);

    expect(fn (): Communication => $action->handle(new DurableIdempotencyNotifiable, new DurableIdempotencyMailNotification, $context))
        ->toThrow(InvalidArgumentException::class, 'Communication idempotency key must not be blank.')
        ->and(Communication::query()->count())->toBe(0)
        ->and(fn (): Communication => app(CommunicationManagerService::class)->notify(
            new DurableIdempotencyNotifiable,
            new DurableIdempotencyMailNotification,
            $context,
        ))->toThrow(InvalidArgumentException::class, 'Communication idempotency key must not be blank.');
});

it('rejects durable duplicates when the cache lock is unavailable', function (): void {
    config()->set('communications.cache.idempotency_store', 'array');

    $manager = app(CommunicationManagerService::class);
    $context = CommunicationContextData::from([
        'purpose' => 'durable-manager',
        'idempotencyKey' => 'durable-manager-key',
    ]);

    $manager->notify(new DurableIdempotencyNotifiable, new DurableIdempotencyMailNotification, $context);

    Cache::store('array')->flush();

    expect(fn (): Communication => $manager->notify(new DurableIdempotencyNotifiable, new DurableIdempotencyMailNotification, $context))
        ->toThrow(RuntimeException::class, 'Duplicate communication detected for idempotency key: durable-manager-key')
        ->and(Communication::query()->where('idempotency_key', 'durable-manager-key')->count())->toBe(1);
});

it('scopes durable idempotency keys to the owning record', function (): void {
    Queue::fake();

    $ownerA = User::query()->create([
        'name' => 'Durable Owner A',
        'email' => 'durable-owner-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Durable Owner B',
        'email' => 'durable-owner-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $action = app(DispatchManagedNotificationAction::class);
    $context = CommunicationContextData::from([
        'purpose' => 'durable-owner-scope',
        'idempotencyKey' => 'durable-owner-key',
    ]);

    $first = OwnerContext::withOwner($ownerA, fn (): Communication => $action->handle(
        new DurableIdempotencyNotifiable,
        new DurableIdempotencyMailNotification,
        $context,
    ));
    $second = OwnerContext::withOwner($ownerB, fn (): Communication => $action->handle(
        new DurableIdempotencyNotifiable,
        new DurableIdempotencyMailNotification,
        $context,
    ));

    expect($second->id)->not->toBe($first->id)
        ->and($first->owner_id)->toBe((string) $ownerA->getKey())
        ->and($second->owner_id)->toBe((string) $ownerB->getKey())
        ->and(Communication::query()->withoutOwnerScope()->where('idempotency_key', 'durable-owner-key')->count())->toBe(2);
});

it('enforces communication reference uniqueness while allowing distinct roles', function (): void {
    $communication = (new Communication)->forceFill([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'durable-reference',
        'status' => CommunicationStatus::Draft,
    ]);
    $communication->save();

    $action = app(AttachCommunicationReferenceAction::class);

    $nullRole = $action->handle($communication->id, 'events', 'event-durable-1');
    $nullRoleAgain = $action->handle($communication->id, 'events', 'event-durable-1');
    $roleEvent = $action->handle($communication->id, 'events', 'event-durable-1', 'event');
    $roleSubject = $action->handle($communication->id, 'events', 'event-durable-1', 'subject');

    expect($nullRoleAgain->id)->toBe($nullRole->id)
        ->and($roleEvent->id)->not->toBe($roleSubject->id)
        ->and(CommunicationReference::query()->where('communication_id', $communication->id)->count())->toBe(3);

    expect(fn (): bool => DB::table((new CommunicationReference)->getTable())->insert([
        'id' => (string) Str::uuid(),
        'communication_id' => $communication->id,
        'reference_type' => 'events',
        'reference_id' => 'event-durable-1',
        'role' => null,
        'created_at' => CarbonImmutable::now(),
        'updated_at' => CarbonImmutable::now(),
    ]))->toThrow(QueryException::class);
});

it('isolates manager idempotency cache locks across owners and avoids mutating duplicate context-aware notifications', function (): void {
    Queue::fake();
    config()->set('communications.cache.idempotency_store', 'array');

    $ownerA = User::query()->create([
        'name' => 'Lock Owner A',
        'email' => 'lock-owner-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Lock Owner B',
        'email' => 'lock-owner-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $manager = app(CommunicationManagerService::class);
    $context = CommunicationContextData::from([
        'purpose' => 'owner-lock-isolation',
        'idempotencyKey' => 'shared-tenant-lock-key',
    ]);

    $first = OwnerContext::withOwner($ownerA, fn (): Communication => $manager->notify(
        new DurableIdempotencyNotifiable,
        new DurableIdempotencyMailNotification,
        $context,
    ));
    $second = OwnerContext::withOwner($ownerB, fn (): Communication => $manager->notify(
        new DurableIdempotencyNotifiable,
        new DurableIdempotencyMailNotification,
        $context,
    ));

    expect($second->id)->not->toBe($first->id);

    $contextAware = new class extends Notification implements CommunicationContextAware
    {
        public int $calls = 0;

        /** @return array<int, string> */
        public function via(object $notifiable): array
        {
            return ['mail'];
        }

        public function toMail(object $notifiable): MailMessage
        {
            return (new MailMessage)->subject('Durable')->line('Durable');
        }

        public function withCommunicationContext(
            string $communicationId,
            array $deliveryIdsByChannel,
            ?string $ownerType = null,
            string | int | null $ownerId = null,
        ): static {
            $this->calls++;

            return $this;
        }
    };

    OwnerContext::withOwner($ownerA, fn (): Communication => app(DispatchManagedNotificationAction::class)->handle(
        new DurableIdempotencyNotifiable,
        $contextAware,
        $context,
    ));

    expect($contextAware->calls)->toBe(0);
});
