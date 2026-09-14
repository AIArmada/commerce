<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\FilamentTicketing\Resources\TicketTypeResource\RelationManagers\TicketTypeComponentsRelationManager;
use AIArmada\FilamentTicketing\Resources\TicketTypeResource\RelationManagers\TicketTypeProductsRelationManager;
use AIArmada\FilamentTicketing\Support\TicketableReferenceGuard;
use AIArmada\FilamentTicketing\Support\TicketMoney;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Ticketing\Enums\TicketAccessType;
use AIArmada\Ticketing\Enums\TicketTypeStatus;
use AIArmada\Ticketing\Enums\TicketTypeVisibility;
use AIArmada\Ticketing\Models\TicketType;
use AIArmada\Ticketing\Support\TicketableTypeRegistry;
use BackedEnum;
use Closure;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

final class TicketTypeResource extends Resource
{
    protected static ?string $model = TicketType::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    /**
     * Whether the purchase-quantity bounds are inverted. Empty bounds are
     * always acceptable.
     */
    public static function minQuantityExceedsMax(mixed $min, mixed $max): bool
    {
        if ($min === null || $min === '' || $max === null || $max === '') {
            return false;
        }

        return (int) $min > (int) $max;
    }

    public static function canViewAny(): bool
    {
        return FilamentPermission::hasAbility('ticket-type.viewAny');
    }

    public static function canView(Model $record): bool
    {
        return FilamentPermission::hasAbility('ticket-type.view');
    }

    public static function canCreate(): bool
    {
        return FilamentPermission::hasAbility('ticket-type.create');
    }

    public static function canEdit(Model $record): bool
    {
        return FilamentPermission::hasAbility('ticket-type.update');
    }

    public static function canDelete(Model $record): bool
    {
        return FilamentPermission::hasAbility('ticket-type.delete');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-ticketing.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-ticketing.resources.navigation_sort.ticket_type');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function getEloquentQuery(): Builder
    {
        $ticketableTypes = app(TicketableTypeRegistry::class)->all();
        $query = OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false);

        if ($ticketableTypes === []) {
            return $query;
        }

        return $query
            ->whereHasMorph(
                'ticketable',
                $ticketableTypes,
                fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false),
            );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                MorphToSelect::make('ticketable')
                    ->types(
                        fn (TicketableTypeRegistry $registry) => collect($registry->all())->map(
                            fn (string $class): MorphToSelect\Type => MorphToSelect\Type::make($class)
                                ->titleAttribute(TicketableReferenceGuard::titleAttributeFor($class))
                                ->searchColumns(TicketableReferenceGuard::searchColumnsFor($class))
                                ->modifyOptionsQueryUsing(
                                    static fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false)
                                )
                        )->toArray()
                    )
                    ->searchable()
                    ->required(),
                Section::make('Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('code')
                            ->required()
                            ->maxLength(50)
                            // Mirrors the database unique on
                            // (ticketable_type, ticketable_id, code).
                            ->unique(ignoreRecord: true, modifyRuleUsing: static function (Unique $rule, Get $get): Unique {
                                return $rule
                                    ->where('ticketable_type', (string) $get('ticketable_type'))
                                    ->where('ticketable_id', (string) $get('ticketable_id'));
                            }),
                        Textarea::make('description')
                            ->maxLength(65535)
                            ->columnSpanFull(),
                        Select::make('access_type')
                            ->options(TicketAccessType::options())
                            ->required(),
                        Select::make('seating_mode')
                            ->options(SeatingMode::options())
                            ->nullable(),
                        TextInput::make('price')
                            ->numeric()
                            ->minValue(0)
                            ->suffix(fn (Get $get): string => (string) ($get('currency') ?? 'MYR'))
                            ->helperText('Ticket price in major units; stored as integer minor units.')
                            ->formatStateUsing(
                                static fn (?int $state): ?string => TicketMoney::toDisplay($state)
                            )
                            ->dehydrateStateUsing(
                                static fn (?string $state): ?int => TicketMoney::toMinor($state)
                            ),
                        TextInput::make('currency')
                            ->alpha()
                            ->maxLength(3)
                            ->default('MYR')
                            ->dehydrateStateUsing(
                                static fn (?string $state): ?string => $state !== null ? mb_strtoupper($state) : null
                            ),
                        TextInput::make('admits_quantity')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->default(1),
                        TextInput::make('min_quantity')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->nullable()
                            ->rules([static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                if (self::minQuantityExceedsMax($value, $get('max_quantity'))) {
                                    $fail('The minimum quantity must not exceed the maximum quantity.');
                                }
                            }]),
                        TextInput::make('max_quantity')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->nullable(),
                    ])->columns(2),
                Section::make('Sales & Visibility')
                    ->schema([
                        Select::make('status')
                            ->options(TicketTypeStatus::options())
                            ->required(),
                        Select::make('visibility')
                            ->options(TicketTypeVisibility::options())
                            ->required(),
                        DateTimePicker::make('sales_starts_at')
                            ->nullable(),
                        DateTimePicker::make('sales_ends_at')
                            ->nullable()
                            ->rules(['after_or_equal:sales_starts_at']),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('code')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('access_type')
                    ->badge()
                    ->color(fn (string $state): string => TicketAccessType::tryFrom($state)?->color() ?? 'gray'),
                Tables\Columns\TextColumn::make('price')
                    ->formatStateUsing(
                        static fn (?int $state, TicketType $record): string => $state === null
                            ? '—'
                            : MoneyFormatter::formatMinor($state, $record->currency ?? 'MYR')
                    )
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => TicketTypeStatus::tryFrom($state)?->color() ?? 'gray'),
                Tables\Columns\TextColumn::make('visibility')
                    ->badge()
                    ->color(fn (TicketTypeVisibility | string $state): string => match ($state instanceof TicketTypeVisibility ? $state->value : $state) {
                        'public' => 'success',
                        'private' => 'warning',
                        'hidden' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('sales_starts_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sales_ends_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(TicketTypeStatus::options()),
                Tables\Filters\SelectFilter::make('access_type')
                    ->options(TicketAccessType::options()),
                Tables\Filters\SelectFilter::make('visibility')
                    ->options(TicketTypeVisibility::options()),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Ticket Type Details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('code')->badge(),
                        TextEntry::make('description'),
                        TextEntry::make('access_type')->badge(),
                        TextEntry::make('seating_mode'),
                        TextEntry::make('price')
                            ->formatStateUsing(
                                static fn (?int $state, TicketType $record): string => $state === null
                                    ? '—'
                                    : MoneyFormatter::formatMinor($state, $record->currency ?? 'MYR')
                            ),
                        TextEntry::make('currency'),
                        TextEntry::make('admits_quantity'),
                        TextEntry::make('min_quantity'),
                        TextEntry::make('max_quantity'),
                        TextEntry::make('sales_starts_at')->dateTime(),
                        TextEntry::make('sales_ends_at')->dateTime(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('visibility')->badge(),
                        TextEntry::make('sort_order'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ])->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TicketTypeComponentsRelationManager::class,
            TicketTypeProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => TicketTypeResource\Pages\ListTicketTypes::route('/'),
            'create' => TicketTypeResource\Pages\CreateTicketType::route('/create'),
            'view' => TicketTypeResource\Pages\ViewTicketType::route('/{record}'),
            'edit' => TicketTypeResource\Pages\EditTicketType::route('/{record}/edit'),
        ];
    }
}
