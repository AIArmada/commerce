<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentSignals;

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\FilamentSignals\FilamentSignalsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider as FilamentSupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Livewire\LivewireServiceProvider;

abstract class FilamentSignalsTestCase extends SignalsTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            LivewireServiceProvider::class,
            FilamentSupportServiceProvider::class,
            ActionsServiceProvider::class,
            SchemasServiceProvider::class,
            FormsServiceProvider::class,
            TablesServiceProvider::class,
            FilamentServiceProvider::class,
            FilamentSignalsServiceProvider::class,
        ];
    }
}
