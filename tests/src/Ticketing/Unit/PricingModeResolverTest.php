<?php

declare(strict_types=1);

use AIArmada\Ticketing\Enums\PricingMode;
use AIArmada\Ticketing\Models\TicketType;
use AIArmada\Ticketing\Support\PricingModeResolver;
use Illuminate\Database\Eloquent\Collection;

it('resolves to mixed when collection has both paid and free types', function (): void {
    $types = new Collection([
        TicketType::factory()->make(['price' => 50000]),
        TicketType::factory()->make(['price' => null]),
    ]);

    $mode = PricingModeResolver::resolve($types);

    expect($mode)->toBe(PricingMode::Mixed);
});

it('resolves to paid when all types have a positive price', function (): void {
    $types = new Collection([
        TicketType::factory()->make(['price' => 10000]),
        TicketType::factory()->make(['price' => 20000]),
    ]);

    $mode = PricingModeResolver::resolve($types);

    expect($mode)->toBe(PricingMode::Paid);
});

it('resolves to free when all types have no price', function (): void {
    $types = new Collection([
        TicketType::factory()->make(['price' => null]),
        TicketType::factory()->make(['price' => 0]),
    ]);

    $mode = PricingModeResolver::resolve($types);

    expect($mode)->toBe(PricingMode::Free);
});
