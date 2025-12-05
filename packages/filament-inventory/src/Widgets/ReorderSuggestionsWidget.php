<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Widgets;

use AIArmada\Inventory\Enums\ReorderUrgency;
use AIArmada\Inventory\Models\InventoryReorderSuggestion;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

final class ReorderSuggestionsWidget extends TableWidget
{
    protected static ?int $sort = 40;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Reorder Suggestions';

    public static function canView(): bool
    {
        return config('filament-inventory.features.reorder_suggestions_widget', true);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InventoryReorderSuggestion::query()
                    ->pending()
                    ->byUrgency()
                    ->with(['location', 'supplierLeadtime'])
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('inventoryable_type')
                    ->label('Product Type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->toggleable(),

                TextColumn::make('location.name')
                    ->label('Location')
                    ->placeholder('All locations'),

                TextColumn::make('current_stock')
                    ->label('Current')
                    ->numeric()
                    ->alignEnd(),

                TextColumn::make('reorder_point')
                    ->label('Reorder Point')
                    ->numeric()
                    ->alignEnd(),

                TextColumn::make('suggested_quantity')
                    ->label('Suggested Qty')
                    ->numeric()
                    ->alignEnd()
                    ->weight('bold'),

                TextColumn::make('expected_stockout_date')
                    ->label('Stockout Date')
                    ->date()
                    ->placeholder('Unknown')
                    ->color(fn (?InventoryReorderSuggestion $record): string => match (true) {
                        $record?->expected_stockout_date === null => 'gray',
                        $record->expected_stockout_date->isPast() => 'danger',
                        $record->expected_stockout_date->diffInDays(now()) <= 7 => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('urgency')
                    ->badge()
                    ->color(fn (InventoryReorderSuggestion $record): string => $record->urgency->color()),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (InventoryReorderSuggestion $record): string => $record->status->color()),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (InventoryReorderSuggestion $record) => $record->approve(auth()->id())),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (InventoryReorderSuggestion $record) => $record->reject()),
            ])
            ->emptyStateHeading('No Pending Suggestions')
            ->emptyStateDescription('All stock levels are healthy.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
