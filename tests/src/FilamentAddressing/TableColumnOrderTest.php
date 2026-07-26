<?php

declare(strict_types=1);

use AIArmada\FilamentAddressing\Tables\AddressAreaTable;
use AIArmada\FilamentAddressing\Tables\AddressCityTable;
use AIArmada\FilamentAddressing\Tables\AddressCountryTable;
use AIArmada\FilamentAddressing\Tables\AddressSnapshotTable;
use AIArmada\FilamentAddressing\Tables\AddressStateTable;
use AIArmada\FilamentAddressing\Tables\AddressTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

it('starts each addressing table with its primary identity column', function (): void {
    $tables = [
        AddressAreaTable::class => 'name',
        AddressCityTable::class => 'name',
        AddressCountryTable::class => 'name',
        AddressSnapshotTable::class => 'formatted_address',
        AddressStateTable::class => 'name',
        AddressTable::class => 'label',
    ];

    foreach ($tables as $tableClass => $expectedColumn) {
        $table = $tableClass::make(Table::make(Mockery::mock(HasTable::class)));

        expect(array_key_first($table->getColumns()))->toBe($expectedColumn);
    }
});
