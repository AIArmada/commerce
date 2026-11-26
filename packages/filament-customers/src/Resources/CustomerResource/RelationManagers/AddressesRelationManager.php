<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources\CustomerResource\RelationManagers;

use AIArmada\Addressing\Contracts\AddressAttachmentWriter;
use AIArmada\Addressing\Contracts\AddressWriter;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Support\ModelResolver;
use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\LikeSearch;
use AIArmada\Customers\Actions\SetDefaultCustomerAddress;
use AIArmada\Customers\Models\Customer;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use LogicException;

class AddressesRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

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
                    ->minLength(2)
                    ->maxLength(2)
                    ->rule('alpha:ascii'),

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
                Action::make('attach')
                    ->label('Add Address')
                    ->form([
                        Forms\Components\Select::make('address_id')
                            ->label('Address')
                            ->searchable()
                            ->required()
                            ->getSearchResultsUsing(function (string $search): array {
                                $needle = LikeSearch::contains($search);

                                return $this->availableAddresses()
                                    ->where(function (Builder $query) use ($needle): void {
                                        LikeSearch::whereLike($query, 'line1', $needle);
                                        LikeSearch::orWhereLike($query, 'city', $needle);
                                        LikeSearch::orWhereLike($query, 'label', $needle);
                                    })
                                    ->orderBy('line1')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (Address $address): array => [
                                        (string) $address->getKey() => $address->formatted_address ?: (string) $address->line1,
                                    ])->all();
                            })
                            ->getOptionLabelUsing(function (mixed $value): ?string {
                                $address = $this->availableAddresses()->whereKey($value)->first();

                                return $address instanceof Address ? ($address->formatted_address ?: (string) $address->line1) : null;
                            }),
                    ])
                    ->action(function (array $data): void {
                        $customer = $this->authorizedCustomer();
                        $address = $this->availableAddresses()->whereKey($data['address_id'] ?? null)->firstOrFail();

                        Gate::forUser(Filament::auth()->user())->authorize('view', $address);
                        $customer->attachAddress($address);
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->using(function (Address $record, array $data): Address {
                        $this->authorizedCustomer();
                        Gate::forUser(Filament::auth()->user())->authorize('update', $record);
                        abort_unless(OwnerUiScope::canMutateRecord($record), 403);

                        app(AddressWriter::class)->execute($data, null, (string) $record->getKey());

                        return $record->refresh();
                    }),
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
                Action::make('detach')
                    ->label('Remove')
                    ->requiresConfirmation()
                    ->action(function (Address $record): void {
                        $this->authorizedCustomer();
                        Gate::forUser(Filament::auth()->user())->authorize('update', $record);
                        abort_unless(OwnerUiScope::canMutateRecord($record), 403);

                        $pivotId = $record->pivot?->getKey();
                        abort_unless(is_string($pivotId) && $pivotId !== '', 403);
                        app(AddressAttachmentWriter::class)->detach($pivotId);
                    }),
            ]);
    }

    private function authorizedCustomer(): Customer
    {
        $customer = $this->getOwnerRecord();
        $user = Filament::auth()->user();

        abort_unless($user !== null && $customer instanceof Customer, 403);
        Gate::forUser($user)->authorize('update', $customer);
        abort_unless(OwnerUiScope::canMutateRecord($customer), 403);

        return $customer;
    }

    /** @return Builder<Address> */
    private function availableAddresses(): Builder
    {
        $customer = $this->authorizedCustomer();

        return OwnerUiScope::applyForRecordOwner(ModelResolver::addressClass()::query(), $customer);
    }

    private static function isPrimaryForType(Address $address, string $type): bool
    {
        return (string) ($address->pivot?->type ?? '') === $type
            && (bool) ($address->pivot?->is_primary ?? false);
    }
}
