<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\FilamentTicketing\Resources\TicketTypeResource\RelationManagers\TicketTypeComponentsRelationManager;
use AIArmada\FilamentTicketing\Resources\TicketTypeResource\RelationManagers\TicketTypeProductsRelationManager;
use AIArmada\FilamentTicketing\Schemas\TicketTypeFormSchema;
use AIArmada\FilamentTicketing\Tables\TicketTypeTable;
use AIArmada\Ticketing\Models\TicketType;
use AIArmada\Ticketing\Support\TicketableTypeRegistry;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
        return $schema->schema(TicketTypeFormSchema::make());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(TicketTypeTable::columns())
            ->filters(TicketTypeTable::filters())
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
