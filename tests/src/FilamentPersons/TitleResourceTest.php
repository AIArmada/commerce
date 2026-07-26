<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\FilamentPersons\Resources\TitleResource;
use AIArmada\Persons\Models\Title;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

it('shows the configured country column for titles', function (): void {
    $originalCountryClass = config('persons.models.country');

    config()->set('persons.models.country', AddressCountry::class);

    try {
        $table = TitleResource::table(Table::make(Mockery::mock(HasTable::class)));
        $countryColumn = $table->getColumn('country.name');
        $relation = (new Title)->country();

        expect($countryColumn)
            ->toBeInstanceOf(TextColumn::class)
            ->and($relation->getRelated())
            ->toBeInstanceOf(AddressCountry::class);
    } finally {
        config()->set('persons.models.country', $originalCountryClass);
    }
});
