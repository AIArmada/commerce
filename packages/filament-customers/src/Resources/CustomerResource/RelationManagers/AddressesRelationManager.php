<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources\CustomerResource\RelationManagers;

use AIArmada\Addressing\Models\Address;
use AIArmada\Customers\Actions\SetDefaultCustomerAddress;
use AIArmada\Customers\Models\Customer;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use LogicException;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static ?string $title = 'Addresses';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('label')
                    ->label('Label')
                    ->maxLength(255),

                Forms\Components\TextInput::make('country_code')
                    ->label('Country')
                    ->required()
                    ->maxLength(2),

                Forms\Components\TextInput::make('line1')
                    ->label('Address Line 1')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('line2')
                    ->label('Address Line 2')
                    ->maxLength(255),

                Grid::make(3)
                    ->schema([
                        Forms\Components\TextInput::make('city')
                            ->label('City')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('state')
                            ->label('State / Region')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('postcode')
                            ->label('Postcode')
                            ->required()
                            ->maxLength(20),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('label')
                    ->searchable(),
                TextColumn::make('line1')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('city')
                    ->searchable(),
                TextColumn::make('state')
                    ->searchable(),
                TextColumn::make('postcode'),
                TextColumn::make('country_code')
                    ->label('Country'),
                TextColumn::make('pivot.type')
                    ->label('Type')
                    ->badge(),
                IconColumn::make('pivot.is_primary')
                    ->label('Primary')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Add Address')
                    ->preloadRecordSelect(),
            ])
            ->actions([
                EditAction::make(),
                Action::make('set_billing')
                    ->label('Set as Billing')
                    ->icon('heroicon-o-credit-card')
                    ->action(function (Address $record): void {
                        $user = Filament::auth()->user();

                        if ($user === null) {
                            abort(403);
                        }

                        Gate::forUser($user)->authorize('update', $record);

                        $customer = $this->getOwnerRecord();

                        if (! $customer instanceof Customer) {
                            throw new LogicException('Customer address actions require a customer owner record.');
                        }

                        app(SetDefaultCustomerAddress::class)->execute($customer, $record, 'billing');
                    })
                    ->visible(fn (Address $record): bool => ! self::isPrimaryForType($record, 'billing')),
                Action::make('set_shipping')
                    ->label('Set as Shipping')
                    ->icon('heroicon-o-truck')
                    ->action(function (Address $record): void {
                        $user = Filament::auth()->user();

                        if ($user === null) {
                            abort(403);
                        }

                        Gate::forUser($user)->authorize('update', $record);

                        $customer = $this->getOwnerRecord();

                        if (! $customer instanceof Customer) {
                            throw new LogicException('Customer address actions require a customer owner record.');
                        }

                        app(SetDefaultCustomerAddress::class)->execute($customer, $record, 'shipping');
                    })
                    ->visible(fn (Address $record): bool => ! self::isPrimaryForType($record, 'shipping')),
                DetachAction::make()
                    ->label('Remove'),
            ]);
    }

    private static function isPrimaryForType(Address $address, string $type): bool
    {
        return (string) ($address->pivot?->type ?? '') === $type
            && (bool) ($address->pivot?->is_primary ?? false);
    }
}
