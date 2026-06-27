<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Models\EventTicketType;
use AIArmada\FilamentEvents\Actions\Exporter\EventTicketTypeExporter;
use AIArmada\FilamentEvents\Actions\Importer\EventTicketTypeImporter;
use AIArmada\FilamentEvents\Resources\EventTicketTypeResource\RelationManagers\EventTicketTypeBundleProductsRelationManager;
use AIArmada\FilamentEvents\Resources\EventTicketTypeResource\RelationManagers\EventTicketTypeComponentsRelationManager;
use BackedEnum;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class EventTicketTypeResource extends Resource
{
    protected static ?string $model = EventTicketType::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-ticket';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-events.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-events.resources.navigation_sort.ticket_type');

        return is_numeric($sort) ? (int) $sort : null;
    }

    /* @phpstan-ignore return.type */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<EventTicketType> $query */
        $query = parent::getEloquentQuery();

        return $query
            ->whereHas('event', fn (Builder $eventQuery): Builder => OwnerUiScope::apply($eventQuery, includeGlobal: false))
            ->with(['event', 'occurrence', 'session']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event'),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('code')
                    ->badge(),
                Tables\Columns\TextColumn::make('access_type')
                    ->badge()
                    ->color(fn (mixed $state): string => match ((string) $state) {
                        'public' => 'success',
                        'private' => 'danger',
                        'invite_only' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('price')
                    ->money(fn (EventTicketType $record): string => $record->currency),
                Tables\Columns\TextColumn::make('currency'),
                Tables\Columns\TextColumn::make('getTotalOnHand')
                    ->label('Stock')
                    ->numeric(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (mixed $state): string => match ((string) $state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'sold_out' => 'danger',
                        'paused' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('sales_starts_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('sales_ends_at')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('access_type')
                    ->options([
                        'public' => 'Public',
                        'private' => 'Private',
                        'invite_only' => 'Invite Only',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'sold_out' => 'Sold Out',
                        'paused' => 'Paused',
                    ]),
            ])
            ->headerActions([
                ImportAction::make()
                    ->importer(EventTicketTypeImporter::class)
                    ->label('Import Ticket Types'),
                ExportAction::make()
                    ->exporter(EventTicketTypeExporter::class)
                    ->label('Export Ticket Types'),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Ticket Type Details')
                    ->schema([
                        TextEntry::make('event.title'),
                        TextEntry::make('occurrence.title'),
                        TextEntry::make('session.title'),
                        TextEntry::make('name'),
                        TextEntry::make('code')->badge(),
                        TextEntry::make('description'),
                        TextEntry::make('access_type')->badge(),
                        TextEntry::make('seating_mode')->badge(),
                        TextEntry::make('price')
                            ->money(fn (EventTicketType $record): string => $record->currency),
                        TextEntry::make('currency'),
                        TextEntry::make('getTotalOnHand')
                            ->label('Stock (On Hand)')
                            ->numeric(),
                        TextEntry::make('getTotalAvailable')
                            ->label('Stock (Available)')
                            ->numeric(),
                        TextEntry::make('admits_quantity')->numeric(),
                        TextEntry::make('min_quantity')->numeric(),
                        TextEntry::make('max_quantity')->numeric(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('visibility')->badge(),
                        TextEntry::make('sort_order')->numeric(),
                        TextEntry::make('sales_starts_at')->dateTime(),
                        TextEntry::make('sales_ends_at')->dateTime(),
                    ])->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            EventTicketTypeBundleProductsRelationManager::class,
            EventTicketTypeComponentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => EventTicketTypeResource\Pages\ListEventTicketTypes::route('/'),
            'view' => EventTicketTypeResource\Pages\ViewEventTicketType::route('/{record}'),
        ];
    }
}
