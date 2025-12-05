<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Actions;

use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

final class CycleCountAction
{
    /**
     * Create the cycle count action for a record.
     */
    public static function make(string $name = 'cycle_count'): Action
    {
        return Action::make($name)
            ->label('Cycle Count')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('gray')
            ->modalHeading('Perform Cycle Count')
            ->modalDescription('Verify inventory quantity at a specific location.')
            ->form([
                Grid::make(2)
                    ->schema([
                        Select::make('location_id')
                            ->label('Location')
                            ->options(InventoryLocation::query()->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, Model $record): void {
                                if ($state === null) {
                                    return;
                                }

                                $stockLevel = InventoryLevel::query()
                                    ->where('inventoryable_type', $record->getMorphClass())
                                    ->where('inventoryable_id', $record->getKey())
                                    ->where('location_id', $state)
                                    ->first();

                                $set('system_quantity', $stockLevel?->quantity_on_hand ?? 0);
                            }),

                        TextInput::make('system_quantity')
                            ->label('System Quantity')
                            ->disabled()
                            ->default(0),

                        TextInput::make('counted_quantity')
                            ->label('Counted Quantity')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->autofocus(),

                        TextInput::make('counter')
                            ->label('Counted By')
                            ->placeholder('Name of person counting...')
                            ->maxLength(100),
                    ]),
            ])
            ->action(function (Model $record, array $data): void {
                $locationId = $data['location_id'];
                $countedQuantity = (int) $data['counted_quantity'];
                $systemQuantity = (int) $data['system_quantity'];
                $variance = $countedQuantity - $systemQuantity;

                if ($variance === 0) {
                    Notification::make()
                        ->title('Count Verified')
                        ->body('System quantity matches counted quantity. No adjustment needed.')
                        ->success()
                        ->send();

                    return;
                }

                $inventoryService = app(InventoryService::class);

                $reason = 'Cycle Count';
                if (isset($data['counter'])) {
                    $reason .= " by {$data['counter']}";
                }

                $inventoryService->adjust(
                    model: $record,
                    locationId: $locationId,
                    newQuantity: $countedQuantity,
                    reason: $reason,
                    note: "Variance: {$variance} (System: {$systemQuantity}, Counted: {$countedQuantity})",
                    userId: auth()->id(),
                );

                $varianceText = $variance > 0 ? "+{$variance}" : (string) $variance;

                Notification::make()
                    ->title('Count Completed')
                    ->body("Adjusted quantity from {$systemQuantity} to {$countedQuantity} (variance: {$varianceText}).")
                    ->warning()
                    ->send();
            });
    }
}
