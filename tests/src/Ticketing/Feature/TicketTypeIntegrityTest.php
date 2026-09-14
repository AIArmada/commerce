<?php

declare(strict_types=1);

use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Ticketing\Actions\EnsureTicketTypeAction;
use AIArmada\Ticketing\Enums\TicketTypeStatus;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;
use AIArmada\Ticketing\Models\PassTransfer;
use AIArmada\Ticketing\Models\TicketType;
use AIArmada\Ticketing\Models\TicketTypeComponent;
use AIArmada\Ticketing\Models\TicketTypeProduct;
use AIArmada\Ticketing\Models\TicketTypeSeatingOption;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('aggregates available inventory in sql with per-level clamping', function (): void {
    $ticketType = TicketType::factory()->create();
    $morph = $ticketType->getMorphClass();

    InventoryLevel::factory()->forInventoryable($morph, $ticketType->getKey())->create([
        'quantity_on_hand' => 100,
        'quantity_reserved' => 10,
    ]);
    InventoryLevel::factory()->forInventoryable($morph, $ticketType->getKey())->create([
        'quantity_on_hand' => 50,
        'quantity_reserved' => 60,
    ]);

    DB::enableQueryLog();
    $available = $ticketType->getTotalAvailable();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($available)->toBe(90)
        ->and($queries)->toHaveCount(1)
        ->and($queries[0]['query'])->toContain('sum');
});

it('rejects duplicate ticket type codes within one ticketable', function (): void {
    $ticketType = TicketType::factory()->create();

    expect(fn () => TicketType::factory()->create([
        'ticketable_type' => $ticketType->ticketable_type,
        'ticketable_id' => $ticketType->ticketable_id,
        'code' => $ticketType->code,
    ]))->toThrow(QueryException::class);
});

it('returns the existing ticket type when ensured twice', function (): void {
    $ticketable = TicketType::factory()->create();

    $first = app(EnsureTicketTypeAction::class)->handle($ticketable, ['code' => 'DUPE', 'name' => 'Dupe']);
    $second = app(EnsureTicketTypeAction::class)->handle($ticketable, ['code' => 'DUPE', 'name' => 'Dupe']);

    expect($second->getKey())->toBe($first->getKey())
        ->and(TicketType::query()->where('code', 'DUPE')->count())->toBe(1);
});

it('rejects duplicate component pairs', function (): void {
    $component = TicketTypeComponent::factory()->create();

    expect(fn () => TicketTypeComponent::factory()->create([
        'parent_ticket_type_id' => $component->parent_ticket_type_id,
        'component_ticket_type_id' => $component->component_ticket_type_id,
    ]))->toThrow(QueryException::class);
});

it('indexes the transfer expiry scan column', function (): void {
    $indexes = Schema::getIndexes(config('ticketing.database.tables.passes', 'ticket_passes'));

    $columns = collect($indexes)->flatMap(fn (array $index): array => $index['columns'] ?? [])->all();

    expect($columns)->toContain('transfer_expires_at');
});

it('rejects ticket type statuses outside the enum', function (): void {
    $ticketType = TicketType::factory()->make(['status' => 'bogus']);

    expect(fn () => $ticketType->save())->toThrow(InvalidArgumentException::class, 'Invalid ticket type status');
});

it('accepts every enum ticket type status', function (): void {
    foreach (TicketTypeStatus::cases() as $status) {
        $ticketType = TicketType::factory()->create(['status' => $status->value]);

        expect($ticketType->status)->toBe($status->value);
    }
});

it('removes holders and transfers when a pass is deleted', function (): void {
    $pass = Pass::factory()->create();
    PassHolder::factory()->count(2)->create(['pass_id' => $pass->getKey()]);
    PassTransfer::factory()->create(['pass_id' => $pass->getKey()]);

    $pass->delete();

    expect(PassHolder::query()->where('pass_id', $pass->getKey())->count())->toBe(0)
        ->and(PassTransfer::query()->where('pass_id', $pass->getKey())->count())->toBe(0);
});

it('detaches passes and removes owned rows when a ticket type is deleted', function (): void {
    $ticketType = TicketType::factory()->create();
    $other = TicketType::factory()->create();

    $pass = Pass::factory()->create(['ticket_type_id' => $ticketType->getKey()]);
    TicketTypeComponent::factory()->create([
        'parent_ticket_type_id' => $ticketType->getKey(),
        'component_ticket_type_id' => $other->getKey(),
    ]);
    TicketTypeComponent::factory()->create([
        'parent_ticket_type_id' => $other->getKey(),
        'component_ticket_type_id' => $ticketType->getKey(),
    ]);
    TicketTypeProduct::factory()->create(['ticket_type_id' => $ticketType->getKey()]);
    TicketTypeSeatingOption::factory()->create(['ticket_type_id' => $ticketType->getKey()]);

    $ticketType->delete();

    expect($pass->fresh()?->ticket_type_id)->toBeNull()
        ->and(TicketTypeComponent::query()
            ->where('parent_ticket_type_id', $ticketType->getKey())
            ->orWhere('component_ticket_type_id', $ticketType->getKey())
            ->count())->toBe(0)
        ->and(TicketTypeProduct::query()->where('ticket_type_id', $ticketType->getKey())->count())->toBe(0)
        ->and(TicketTypeSeatingOption::query()->where('ticket_type_id', $ticketType->getKey())->count())->toBe(0);
});
