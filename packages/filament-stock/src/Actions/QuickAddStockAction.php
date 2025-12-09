<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock\Actions;

use AIArmada\FilamentStock\Support\StockableTypeRegistry;
use AIArmada\Stock\Services\StockService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Log;
use Throwable;

final class QuickAddStockAction
{
    public static function make(): Action
    {
        $registry = app(StockableTypeRegistry::class);

        return Action::make('quick_add_stock')
            ->label('Quick Add Stock')
            ->icon(Heroicon::OutlinedPlus)
            ->color('success')
            ->modalHeading('Quick Add Stock')
            ->modalDescription('Quickly add stock to a product or item.')
            ->modalSubmitActionLabel('Add Stock')
            ->form(fn (Schema $schema): Schema => $schema->components(self::buildFormSchema($registry)))
            ->action(function (array $data) use ($registry): void {
                try {
                    $stockableType = $data['stockable_type'];
                    $stockableId = $data['stockable_id'];
                    $quantity = (int) $data['quantity'];
                    $reason = $data['reason'] ?? 'restock';
                    $note = $data['note'] ?? null;

                    // Resolve stockable model
                    $morphedModel = Relation::getMorphedModel($stockableType) ?? $stockableType;

                    if (! class_exists($morphedModel)) {
                        Notification::make()
                            ->warning()
                            ->title('Invalid Model')
                            ->body('The stockable type is not valid.')
                            ->icon(Heroicon::OutlinedExclamationCircle)
                            ->send();

                        return;
                    }

                    $stockable = $morphedModel::find($stockableId);

                    if (! $stockable) {
                        Notification::make()
                            ->warning()
                            ->title('Item Not Found')
                            ->body('The selected item could not be found.')
                            ->icon(Heroicon::OutlinedExclamationCircle)
                            ->send();

                        return;
                    }

                    $service = app(StockService::class);
                    $transaction = $service->addStock(
                        model: $stockable,
                        quantity: $quantity,
                        reason: $reason,
                        note: $note
                    );

                    $itemName = $registry->resolveLabelForKey($morphedModel, $stockableId) ?? "ID: {$stockableId}";

                    Notification::make()
                        ->success()
                        ->title('Stock Added')
                        ->body("Added {$quantity} units to {$itemName}. New stock: {$service->getCurrentStock($stockable)}")
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->send();

                } catch (Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Unexpected Error')
                        ->body('An unexpected error occurred while adding stock.')
                        ->icon(Heroicon::OutlinedXCircle)
                        ->persistent()
                        ->send();

                    Log::error('Failed to add stock via quick action', [
                        'data' => $data,
                        'error' => $exception->getMessage(),
                        'trace' => $exception->getTraceAsString(),
                    ]);
                }
            });
    }

    /**
     * @return array<Select|TextInput|Textarea>
     */
    private static function buildFormSchema(StockableTypeRegistry $registry): array
    {
        $schema = [];

        if ($registry->hasDefinitions()) {
            $schema[] = Select::make('stockable_type')
                ->label('Item Type')
                ->required()
                ->options($registry->options())
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('stockable_id', null))
                ->dehydrateStateUsing(
                    static fn (?string $state): ?string => $state !== null && $state !== ''
                    ? Relation::getMorphAlias($state)
                    : null
                );

            $schema[] = Select::make('stockable_id')
                ->label('Item')
                ->required()
                ->searchable()
                ->getSearchResultsUsing(static function (Get $get, ?string $search) use ($registry): array {
                    $stockableType = $get('stockable_type');

                    if (! is_string($stockableType) || $stockableType === '') {
                        return [];
                    }

                    return $registry->search($stockableType, $search);
                })
                ->getOptionLabelUsing(static function (Get $get, $value) use ($registry): ?string {
                    $stockableType = $get('stockable_type');

                    if (! is_string($stockableType) || $stockableType === '' || $value === null || $value === '') {
                        return null;
                    }

                    return $registry->resolveLabelForKey($stockableType, $value);
                })
                ->hidden(fn (Get $get): bool => ! is_string($get('stockable_type')) || $get('stockable_type') === '');
        } else {
            $schema[] = TextInput::make('stockable_type')
                ->label('Stockable Type')
                ->required()
                ->helperText('The model class or morph alias');

            $schema[] = TextInput::make('stockable_id')
                ->label('Stockable ID')
                ->required();
        }

        $schema[] = TextInput::make('quantity')
            ->label('Quantity')
            ->numeric()
            ->required()
            ->default(1)
            ->minValue(1)
            ->helperText('Number of units to add');

        $schema[] = Select::make('reason')
            ->label('Reason')
            ->options([
                'restock' => 'Restock',
                'return' => 'Customer Return',
                'adjustment' => 'Inventory Adjustment',
                'other' => 'Other',
            ])
            ->default('restock');

        $schema[] = Textarea::make('note')
            ->label('Note (Optional)')
            ->rows(2)
            ->placeholder('Add any notes about this stock addition...');

        return $schema;
    }
}
