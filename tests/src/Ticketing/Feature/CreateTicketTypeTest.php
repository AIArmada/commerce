<?php

declare(strict_types=1);

use AIArmada\Ticketing\Actions\EnsureTicketTypeAction;
use AIArmada\Ticketing\Contracts\TicketableInterface;
use AIArmada\Ticketing\Enums\PricingMode;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\TicketType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('test_ticketables', function ($table): void {
        $table->uuid('id')->primary();
        $table->timestamps();
    });
    $this->ticketable = new class extends Model implements TicketableInterface
    {
        protected $table = 'test_ticketables';

        public $incrementing = false;

        protected $keyType = 'string';

        public function ticketTypes(): MorphMany
        {
            return $this->morphMany(TicketType::class, 'ticketable');
        }

        public function passes(): MorphMany
        {
            return $this->morphMany(Pass::class, 'ticketable');
        }

        public function effectivePricingMode(): PricingMode
        {
            return PricingMode::Paid;
        }

        public function transferWindowEndsAt(): ?CarbonImmutable
        {
            return null;
        }
    };
    $this->ticketable->id = 'test-id';
    $this->ticketable->save();
});

it('creates a ticket type', function (): void {
    $ticketType = app(EnsureTicketTypeAction::class)->handle($this->ticketable, [
        'name' => 'General Admission',
        'code' => 'GA',
        'price' => 50000,
        'currency' => 'MYR',
    ]);

    expect($ticketType)->toBeInstanceOf(TicketType::class)
        ->and($ticketType->name)->toBe('General Admission')
        ->and($ticketType->code)->toBe('GA')
        ->and($ticketType->price)->toBe(50000);
});

it('validates ticket type status on creation', function (): void {
    $ticketType = app(EnsureTicketTypeAction::class)->handle($this->ticketable, [
        'name' => 'Default Status',
        'code' => 'DS',
    ]);

    expect($ticketType->status)->toBe('active');
});
