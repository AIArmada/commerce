<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Actions;

use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class TransferStockAction
{
    /**
     * Create the transfer stock action for a record.
     */
    public static function make(string $name = 'transfer_stock'): Action
    {
        return Action::make($name)
            ->label('Transfer Stock')
            ->icon('heroicon-o-arrows-right-left')
            ->color('info')
            ->modalHeading('Transfer Stock Between Locations')
            ->modalDescription('Move inventory from one location to another.')
            ->form([
                Grid::make(2)
                    ->schema([
                        Select::make('from_location_id')
                            ->label('From Location')
                            ->options(fn () => InventoryLocation::query()->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('to_location_id', null)),

                        Select::make('to_location_id')
                            ->label('To Location')
                            ->options(fn (callable $get) => InventoryLocation::query()
                                ->when($get('from_location_id'), fn ($query, $fromId) => $query->whereNot('id', $fromId))
                                ->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),

                        TextInput::make('quantity')
                            ->label('Quantity to Transfer')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(1),
                    ]),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2)
                    ->placeholder('Optional notes for this transfer...'),
            ])
            ->action(function (Model $record, array $data): void {
                $inventoryService = app(InventoryService::class);

                try {
                    $movement = $inventoryService->transfer(
                        model: $record,
                        fromLocationId: $data['from_location_id'],
                        toLocationId: $data['to_location_id'],
                        quantity: (int) $data['quantity'],
                        note: $data['notes'] ?? null,
                        userId: auth()->id(),
                    );

                    $fromLocation = InventoryLocation::find($data['from_location_id']);
                    $toLocation = InventoryLocation::find($data['to_location_id']);

                    Notification::make()
                        ->title('Stock Transferred')
                        ->body("Transferred {$data['quantity']} units from {$fromLocation?->name} to {$toLocation?->name}.")
                        ->success()
                        ->send();
                } catch (InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Transfer Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
