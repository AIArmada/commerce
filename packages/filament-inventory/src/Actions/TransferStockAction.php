<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Actions;

use AIArmada\CommerceSupport\Support\LikeSearch;
use AIArmada\Inventory\Actions\TransferInventory;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
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
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search): array {
                                $query = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query());
                                LikeSearch::whereLike($query, 'name', LikeSearch::contains($search));

                                return $query
                                    ->orderBy('name')
                                    ->limit(50)
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->getOptionLabelUsing(fn (mixed $value): ?string => InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                                ->whereKey($value)
                                ->value('name'))
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('to_location_id', null)),

                        Select::make('to_location_id')
                            ->label('To Location')
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search, callable $get): array {
                                $query = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                                    ->when($get('from_location_id'), fn ($query, $fromId) => $query->whereNot('id', $fromId));
                                LikeSearch::whereLike($query, 'name', LikeSearch::contains($search));

                                return $query
                                    ->orderBy('name')
                                    ->limit(50)
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->getOptionLabelUsing(fn (mixed $value): ?string => InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                                ->whereKey($value)
                                ->value('name')),

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
                $fromLocationId = (string) $data['from_location_id'];
                $toLocationId = (string) $data['to_location_id'];

                if ($fromLocationId === $toLocationId) {
                    Notification::make()
                        ->title('Transfer Failed')
                        ->body('From and To locations must be different.')
                        ->danger()
                        ->send();

                    return;
                }

                if (InventoryOwnerScope::isEnabled()) {
                    $allowedLocationCount = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                        ->whereKey([$fromLocationId, $toLocationId])
                        ->count();

                    if ($allowedLocationCount !== 2) {
                        Notification::make()
                            ->title('Transfer Failed')
                            ->body('One or both locations are not available for the current owner context.')
                            ->danger()
                            ->send();

                        return;
                    }
                }

                try {
                    $movement = TransferInventory::run(
                        model: $record,
                        fromLocationId: $fromLocationId,
                        toLocationId: $toLocationId,
                        quantity: (int) $data['quantity'],
                        note: $data['notes'] ?? null,
                        userId: Auth::id(),
                    );

                    $fromLocation = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                        ->whereKey($fromLocationId)
                        ->first();

                    $toLocation = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                        ->whereKey($toLocationId)
                        ->first();

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
