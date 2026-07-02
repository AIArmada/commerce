<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentTicketing\Resources\PassHolderResource;
use AIArmada\FilamentTicketing\Resources\PassResource;
use AIArmada\FilamentTicketing\Resources\PassTransferResource;
use AIArmada\FilamentTicketing\Resources\TicketTypeResource;
use AIArmada\FilamentTicketing\Support\TicketableTypeRegistry;
use AIArmada\FilamentTicketing\Tests\Fixtures\OwnedTicketable;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;
use AIArmada\Ticketing\Models\PassTransfer;
use AIArmada\Ticketing\Models\TicketType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('filament_ticketing_owned_ticketables');
    Schema::create('filament_ticketing_owned_ticketables', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->nullableMorphs('owner');
        $table->string('name');
        $table->timestampsTz();
    });
});

it('keeps programmatic and configured ticketable type registrations', function (): void {
    config()->set('filament-ticketing.ticketable_types', [OwnedTicketable::class]);

    $registry = app(TicketableTypeRegistry::class);

    expect(app(TicketableTypeRegistry::class))->toBe($registry)
        ->and($registry->all())->toBe([OwnedTicketable::class]);
});

it('scopes ticketing resources to the current owner', function (): void {
    app(TicketableTypeRegistry::class)->register(OwnedTicketable::class);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'filament-ticketing-owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'filament-ticketing-owner-b@example.com',
        'password' => 'secret',
    ]);

    $ticketableA = OwnerContext::withOwner($ownerA, fn (): OwnedTicketable => OwnedTicketable::query()->create(['name' => 'Owner A ticketable']));
    $ticketableB = OwnerContext::withOwner($ownerB, fn (): OwnedTicketable => OwnedTicketable::query()->create(['name' => 'Owner B ticketable']));

    $ticketTypeA = TicketType::factory()->create([
        'ticketable_type' => $ticketableA->getMorphClass(),
        'ticketable_id' => $ticketableA->id,
    ]);
    $ticketTypeB = TicketType::factory()->create([
        'ticketable_type' => $ticketableB->getMorphClass(),
        'ticketable_id' => $ticketableB->id,
    ]);

    $passA = OwnerContext::withOwner($ownerA, fn (): Pass => Pass::factory()->create([
        'ticketable_type' => $ticketableA->getMorphClass(),
        'ticketable_id' => $ticketableA->id,
        'ticket_type_id' => $ticketTypeA->id,
    ]));
    $passB = OwnerContext::withOwner($ownerB, fn (): Pass => Pass::factory()->create([
        'ticketable_type' => $ticketableB->getMorphClass(),
        'ticketable_id' => $ticketableB->id,
        'ticket_type_id' => $ticketTypeB->id,
    ]));

    $holderA = PassHolder::factory()->create(['pass_id' => $passA->id]);
    $holderB = PassHolder::factory()->create(['pass_id' => $passB->id]);

    $transferA = PassTransfer::factory()->create([
        'pass_id' => $passA->id,
        'from_holder_id' => $holderA->id,
        'to_holder_id' => PassHolder::factory()->create(['pass_id' => $passA->id])->id,
    ]);
    $transferB = PassTransfer::factory()->create([
        'pass_id' => $passB->id,
        'from_holder_id' => $holderB->id,
        'to_holder_id' => PassHolder::factory()->create(['pass_id' => $passB->id])->id,
    ]);

    expect(OwnerContext::withOwner($ownerA, fn (): array => TicketTypeResource::getEloquentQuery()->pluck('id')->all()))
        ->toBe([$ticketTypeA->id])
        ->and(OwnerContext::withOwner($ownerB, fn (): array => TicketTypeResource::getEloquentQuery()->pluck('id')->all()))
        ->toBe([$ticketTypeB->id])
        ->and(OwnerContext::withOwner($ownerA, fn (): array => PassResource::getEloquentQuery()->pluck('id')->all()))
        ->toBe([$passA->id])
        ->and(OwnerContext::withOwner($ownerB, fn (): array => PassResource::getEloquentQuery()->pluck('id')->all()))
        ->toBe([$passB->id])
        ->and(OwnerContext::withOwner($ownerA, fn (): array => PassHolderResource::getEloquentQuery()->pluck('id')->all()))
        ->toContain($holderA->id)
        ->not->toContain($holderB->id)
        ->and(OwnerContext::withOwner($ownerA, fn (): array => PassTransferResource::getEloquentQuery()->pluck('id')->all()))
        ->toBe([$transferA->id])
        ->and(OwnerContext::withOwner($ownerB, fn (): array => PassTransferResource::getEloquentQuery()->pluck('id')->all()))
        ->toBe([$transferB->id]);
});
