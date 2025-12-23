<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources;

use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Policies\CustomerPolicy;
use AIArmada\FilamentCustomers\Resources\CustomerResource\Pages;
use AIArmada\FilamentCustomers\Resources\CustomerResource\RelationManagers;
use AIArmada\FilamentCustomers\Support\CustomersOwnerScope;
use BackedEnum;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static string | UnitEnum | null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'email';

    public static function getNavigationBadge(): ?string
    {
        $count = CustomersOwnerScope::applyToOwnedQuery(static::getModel()::query())
            ->where('status', CustomerStatus::Active)
            ->count();

        return $count ?: null;
    }

    /**
     * @return Builder<Customer>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Customer> $query */
        $query = parent::getEloquentQuery();

        return CustomersOwnerScope::applyToOwnedQuery($query);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Customer Information')
                            ->schema([
                                Forms\Components\TextInput::make('first_name')
                                    ->label('First Name')
                                    ->required()
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('last_name')
                                    ->label('Last Name')
                                    ->required()
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('phone')
                                    ->label('Phone')
                                    ->tel()
                                    ->maxLength(20),

                                Forms\Components\TextInput::make('company')
                                    ->label('Company')
                                    ->maxLength(255),
                            ])
                            ->columns(2),

                        Section::make('Preferences')
                            ->schema([
                                Forms\Components\Toggle::make('accepts_marketing')
                                    ->label('Accepts Marketing')
                                    ->helperText('Customer has opted in for marketing emails'),

                                Forms\Components\Toggle::make('is_tax_exempt')
                                    ->label('Tax Exempt'),

                                Forms\Components\Textarea::make('tax_exempt_reason')
                                    ->label('Tax Exempt Reason')
                                    ->rows(2)
                                    ->visible(fn (Get $get) => $get('is_tax_exempt')),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Status')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label('Status')
                                    ->options(
                                        collect(CustomerStatus::cases())
                                            ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
                                    )
                                    ->required()
                                    ->default('active'),
                            ]),

                        Section::make('Wallet')
                            ->schema([
                                Forms\Components\TextInput::make('wallet_balance')
                                    ->label('Balance')
                                    ->numeric()
                                    ->prefix('RM')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn ($state) => $state / 100)
                                    ->helperText('Use actions to add/deduct credit'),
                            ]),

                        Section::make('Segments')
                            ->schema([
                                Forms\Components\Select::make('segments')
                                    ->label('Segments')
                                    ->relationship(
                                        name: 'segments',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query): Builder => CustomersOwnerScope::applyToOwnedQuery($query),
                                    )
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->helperText('Manual segment assignment'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Customer')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->description(fn ($record) => $record->email),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),

                Tables\Columns\TextColumn::make('lifetime_value')
                    ->label('LTV')
                    ->money('MYR', divideBy: 100)
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Orders')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('wallet_balance')
                    ->label('Credit')
                    ->money('MYR', divideBy: 100)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('accepts_marketing')
                    ->label('Marketing')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('segments.name')
                    ->label('Segments')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('last_order_at')
                    ->label('Last Order')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(
                        collect(CustomerStatus::cases())
                            ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
                    ),

                Tables\Filters\TernaryFilter::make('accepts_marketing')
                    ->label('Accepts Marketing'),

                Tables\Filters\TernaryFilter::make('is_tax_exempt')
                    ->label('Tax Exempt'),

                Tables\Filters\SelectFilter::make('segments')
                    ->relationship(
                        name: 'segments',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => CustomersOwnerScope::applyToOwnedQuery($query),
                    )
                    ->multiple()
                    ->preload(),

                Tables\Filters\Filter::make('high_value')
                    ->label('High Value (LTV > RM 1,000)')
                    ->query(fn ($query) => $query->where('lifetime_value', '>=', 1000_00)),

                Tables\Filters\Filter::make('recent')
                    ->label('Active (Last 30 days)')
                    ->query(fn ($query) => $query->where('last_login_at', '>=', now()->subDays(30))),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\Action::make('add_credit')
                    ->label('Add Credit')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->modalHeading('Add Store Credit')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount (RM)')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->prefix('RM'),

                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->rows(2),
                    ])
                    ->action(function (Customer $record, array $data): void {
                        $user = Auth::user();
                        abort_unless($user !== null, 403);

                        $policy = new CustomerPolicy;
                        abort_unless($policy->update($user, $record), 403);

                        $amountInCents = (int) ($data['amount'] * 100);
                        $record->addCredit($amountInCents, $data['reason'] ?? null);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Credit Added')
                            ->body("RM {$data['amount']} added to wallet.")
                            ->send();
                    }),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                    \Filament\Actions\BulkAction::make('opt_in_marketing')
                        ->label('Opt-In Marketing')
                        ->icon('heroicon-o-bell')
                        ->action(
                            fn (\Illuminate\Support\Collection $records) => $records->each->optInMarketing()
                        ),
                    \Filament\Actions\BulkAction::make('opt_out_marketing')
                        ->label('Opt-Out Marketing')
                        ->icon('heroicon-o-bell-slash')
                        ->action(
                            fn (\Illuminate\Support\Collection $records) => $records->each->optOutMarketing()
                        ),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Customer Overview')
                    ->schema([
                        TextEntry::make('full_name')
                            ->label('Name'),
                        TextEntry::make('email')
                            ->label('Email')
                            ->copyable(),
                        TextEntry::make('phone')
                            ->label('Phone')
                            ->copyable(),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state->label())
                            ->color(fn ($state) => $state->color()),
                    ])
                    ->columns(4),

                Section::make('Value Metrics')
                    ->schema([
                        TextEntry::make('lifetime_value')
                            ->label('Lifetime Value')
                            ->money('MYR', divideBy: 100)
                            ->size(TextSize::Large),
                        TextEntry::make('total_orders')
                            ->label('Total Orders')
                            ->numeric()
                            ->size(TextSize::Large),
                        TextEntry::make('average_order_value')
                            ->label('AOV')
                            ->getStateUsing(fn ($record) => $record->getAverageOrderValue())
                            ->money('MYR', divideBy: 100)
                            ->size(TextSize::Large),
                        TextEntry::make('wallet_balance')
                            ->label('Wallet Balance')
                            ->money('MYR', divideBy: 100)
                            ->size(TextSize::Large),
                    ])
                    ->columns(4),

                Section::make('Activity')
                    ->schema([
                        TextEntry::make('last_order_at')
                            ->label('Last Order')
                            ->dateTime()
                            ->placeholder('No orders yet'),
                        TextEntry::make('last_login_at')
                            ->label('Last Login')
                            ->dateTime()
                            ->placeholder('Never'),
                        TextEntry::make('created_at')
                            ->label('Customer Since')
                            ->dateTime(),
                    ])
                    ->columns(3),

                Section::make('Segments')
                    ->schema([
                        TextEntry::make('segments.name')
                            ->label('Assigned Segments')
                            ->badge()
                            ->placeholder('No segments'),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AddressesRelationManager::class,
            RelationManagers\WishlistsRelationManager::class,
            RelationManagers\NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'email', 'phone', 'company'];
    }
}
