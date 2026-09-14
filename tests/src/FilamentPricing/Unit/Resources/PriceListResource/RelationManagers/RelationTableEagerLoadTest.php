<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentPricing\Resources\PriceListResource\RelationManagers\PricesRelationManager;
use AIArmada\FilamentPricing\Resources\PriceListResource\RelationManagers\TiersRelationManager;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceTier;
use AIArmada\Pricing\Tests\Concerns\EnsuresPricingSchema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

uses(TestCase::class, EnsuresPricingSchema::class);

beforeEach(function (): void {
    $this->ensurePricingSchema();
});

it('eager loads morph targets on the relation tables', function (): void {
    $livewire = Mockery::mock(HasTable::class);

    $pricesTable = (new PricesRelationManager)->table(Table::make($livewire));
    $pricesQuery = $pricesTable->applyQueryScopes(Price::query());

    expect(array_keys($pricesQuery->getEagerLoads()))->toContain('priceable');

    $tiersTable = (new TiersRelationManager)->table(Table::make($livewire));
    $tiersQuery = $tiersTable->applyQueryScopes(PriceTier::query());

    expect(array_keys($tiersQuery->getEagerLoads()))->toContain('tierable');
});
