<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\FilamentTicketing\Actions\ValidateTicketableReference;
use AIArmada\FilamentTicketing\Support\TicketablePickerColumns;
use AIArmada\FilamentTicketing\Tests\Fixtures\OwnedTicketable;
use AIArmada\Ticketing\Features\TicketTypes\Actions\DeleteTicketType;
use AIArmada\Ticketing\Features\TicketTypes\Actions\SaveTicketType;
use AIArmada\Ticketing\Support\TicketableTypeRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config()->set('ticketing.ticketable_types', [OwnedTicketable::class]);

    app(TicketableTypeRegistry::class)->register(OwnedTicketable::class);

    Schema::dropIfExists('filament_ticketing_owned_ticketables');
    Schema::create('filament_ticketing_owned_ticketables', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->nullableMorphs('owner');
        $table->string('name');
        $table->timestampsTz();
    });
});

function ticketableOwner(string $email): User
{
    return User::query()->create([
        'name' => 'Ticketable Owner',
        'email' => $email,
        'password' => 'secret',
    ]);
}

function ownedTicketable(User $owner, string $name): OwnedTicketable
{
    return OwnerContext::withOwner($owner, fn (): OwnedTicketable => OwnedTicketable::query()->create([
        'name' => $name,
    ]));
}

it('accepts registered ticketables inside the current owner scope', function (): void {
    $owner = ticketableOwner('ticketable-guard-ok@example.com');
    $ticketable = ownedTicketable($owner, 'Guarded Show');

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $data = app(ValidateTicketableReference::class)->handle([
        'ticketable_type' => $ticketable->getMorphClass(),
        'ticketable_id' => (string) $ticketable->getKey(),
    ]);

    expect($data['ticketable_id'])->toBe((string) $ticketable->getKey());
});

it('rejects cross-owner ticketable references', function (): void {
    $ownerA = ticketableOwner('ticketable-guard-a@example.com');
    $foreign = ownedTicketable(ticketableOwner('ticketable-guard-b@example.com'), 'Foreign Show');

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    expect(fn (): array => app(ValidateTicketableReference::class)->handle([
        'ticketable_type' => $foreign->getMorphClass(),
        'ticketable_id' => (string) $foreign->getKey(),
    ]))->toThrow(ValidationException::class);
});

it('rejects unregistered types and missing ids', function (): void {
    $owner = ticketableOwner('ticketable-guard-missing@example.com');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    expect(fn (): array => app(ValidateTicketableReference::class)->handle([
        'ticketable_type' => User::class,
        'ticketable_id' => (string) $owner->getKey(),
    ]))->toThrow(ValidationException::class);

    expect(fn (): array => app(ValidateTicketableReference::class)->handle([
        'ticketable_type' => (new OwnedTicketable)->getMorphClass(),
        'ticketable_id' => '00000000-0000-0000-0000-000000000000',
    ]))->toThrow(ValidationException::class);
});

it('derives search columns only from existing table columns', function (): void {
    expect(TicketablePickerColumns::searchColumnsFor(OwnedTicketable::class))->toBe(['name'])
        ->and(TicketablePickerColumns::titleAttributeFor(OwnedTicketable::class))->toBe('name');
});

it('rejects forged scalar ticketable identifiers', function (): void {
    expect(fn () => app(ValidateTicketableReference::class)->handle([
        'ticketable_type' => OwnedTicketable::class,
        'ticketable_id' => true,
    ]))->toThrow(ValidationException::class);
});

it('keeps the ticket type attached to its original host', function (): void {
    $owner = ticketableOwner('ticketable-write@example.com');
    $first = ownedTicketable($owner, 'First show');
    $second = ownedTicketable($owner, 'Second show');

    OwnerContext::withOwner($owner, function () use ($first, $second): void {
        $data = [
            'name' => 'Admission',
            'code' => 'GENERAL',
            'access_type' => 'general',
            'admits_quantity' => 1,
            'status' => 'active',
            'visibility' => 'public',
            'price' => 5000,
        ];

        $type = app(SaveTicketType::class)->handle($first, $data);
        expect($type->ticketable_id)->toBe($first->getKey());

        expect(fn () => app(SaveTicketType::class)->handle($second, $data, $type))
            ->toThrow(AuthorizationException::class);

        expect(fn () => app(DeleteTicketType::class)->handle($type, $second))
            ->toThrow(AuthorizationException::class);

        expect(app(DeleteTicketType::class)->handle($type, $first))->toBeTrue();
    });
});
