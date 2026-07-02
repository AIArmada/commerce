<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\FilamentTicketing\Resources\TicketTypeResource\RelationManagers\TicketTypeComponentsRelationManager;
use AIArmada\FilamentTicketing\Resources\TicketTypeResource\RelationManagers\TicketTypeProductsRelationManager;
use AIArmada\FilamentTicketing\Support\TicketableTypeRegistry;
use AIArmada\Ticketing\Models\TicketType;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class TicketTypeResource extends Resource
{
    protected static ?string $model = TicketType::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

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

        if ($ticketableTypes === []) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
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
                                ->titleAttribute('name')
                                ->searchColumns(['name', 'code', 'title'])
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
                            ->maxLength(50),
                        Textarea::make('description')
                            ->maxLength(65535)
                            ->columnSpanFull(),
                        Select::make('access_type')
                            ->options([
                                'general_admission' => 'General Admission',
                                'reserved_seating' => 'Reserved Seating',
                                'vip' => 'VIP',
                                'complimentary' => 'Complimentary',
                            ])
                            ->required(),
                        Select::make('seating_mode')
                            ->options([
                                'open' => 'Open',
                                'assigned' => 'Assigned',
                                'unassigned' => 'Unassigned',
                            ])
                            ->nullable(),
                        TextInput::make('price')
                            ->numeric()
                            ->prefix('$'),
                        TextInput::make('currency')
                            ->maxLength(3)
                            ->default('USD'),
                        TextInput::make('admits_quantity')
                            ->numeric()
                            ->required()
                            ->default(1),
                        TextInput::make('min_quantity')
                            ->numeric()
                            ->nullable(),
                        TextInput::make('max_quantity')
                            ->numeric()
                            ->nullable(),
                    ])->columns(2),
                Section::make('Sales & Visibility')
                    ->schema([
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'active' => 'Active',
                                'paused' => 'Paused',
                                'sold_out' => 'Sold Out',
                                'ended' => 'Ended',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required(),
                        Select::make('visibility')
                            ->options([
                                'public' => 'Public',
                                'private' => 'Private',
                                'hidden' => 'Hidden',
                            ])
                            ->required(),
                        DateTimePicker::make('sales_starts_at')
                            ->nullable(),
                        DateTimePicker::make('sales_ends_at')
                            ->nullable(),
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
                    ->color(fn (string $state): string => match ($state) {
                        'general_admission' => 'gray',
                        'reserved_seating' => 'info',
                        'vip' => 'warning',
                        'complimentary' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('price')
                    ->money(fn (TicketType $record): string => $record->currency ?? 'USD'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'active' => 'success',
                        'paused' => 'warning',
                        'sold_out' => 'danger',
                        'ended' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('visibility')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
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
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'sold_out' => 'Sold Out',
                        'ended' => 'Ended',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('access_type')
                    ->options([
                        'general_admission' => 'General Admission',
                        'reserved_seating' => 'Reserved Seating',
                        'vip' => 'VIP',
                        'complimentary' => 'Complimentary',
                    ]),
                Tables\Filters\SelectFilter::make('visibility')
                    ->options([
                        'public' => 'Public',
                        'private' => 'Private',
                        'hidden' => 'Hidden',
                    ]),
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
                        TextEntry::make('price')->money(fn (TicketType $record): string => $record->currency ?? 'USD'),
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
