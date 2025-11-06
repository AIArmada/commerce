<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Actions;

use AIArmada\FilamentCart\Models\Cart as CartModel;
use AIArmada\FilamentCart\Models\CartCondition;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

final class RemoveConditionAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Remove')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Remove Condition')
            ->modalDescription('Are you sure you want to remove this condition from the cart?')
            ->modalSubmitActionLabel('Remove Condition')
            ->action(function (CartCondition $record): void {
                try {
                    // Get the cart instance
                    /** @phpstan-ignore-next-line */
                    $cart = $record->cart;
                    $cartInstance = app(CartInstanceManager::class)
                        /** @phpstan-ignore-next-line */
                        ->resolve($cart->instance, $cart->identifier);

                    if ($record->isItemLevel()) {
                        // Remove item-level condition
                        $success = $cartInstance->removeItemCondition($record->item_id, $record->name);
                    } else {
                        // Remove cart-level condition
                        $success = $cartInstance->removeCondition($record->name);
                    }

                    if ($success) {
                        Notification::make()
                            ->title('Condition Removed')
                            ->body("The '{$record->name}' condition has been removed.")
                            ->success()
                            ->send();
                    } else {
                        throw new Exception('Condition not found or could not be removed');
                    }

                } catch (Exception $e) {
                    Notification::make()
                        ->title('Failed to Remove Condition')
                        ->body('An error occurred while removing the condition: '.$e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function getDefaultName(): string
    {
        return 'removeCondition';
    }

    /**
     * Create action for clearing all conditions from cart
     */
    public static function makeClearAll(): static
    {
        return self::make('clearAllConditions')
            ->label('Clear All Conditions')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Clear All Conditions')
            ->modalDescription('Are you sure you want to remove all conditions from this cart? This action cannot be undone.')
            ->modalSubmitActionLabel('Clear All Conditions')
            ->action(function ($record, $livewire): void {
                // Get the cart record - either directly or from relation manager
                $cart = $record instanceof CartModel ? $record : $livewire->getOwnerRecord();

                try {
                    // Get the cart instance
                    $cartInstance = app(CartInstanceManager::class)
                        ->resolve($cart->instance, $cart->identifier);

                    // Clear all cart-level conditions
                    $cartInstance->clearConditions();

                    // Clear all item-level conditions
                    $items = $cartInstance->getItems();
                    foreach ($items as $item) {
                        $cartInstance->clearItemConditions($item->id);
                    }

                    Notification::make()
                        ->title('All Conditions Cleared')
                        ->body('All conditions have been removed from the cart.')
                        ->success()
                        ->send();

                } catch (Exception $e) {
                    Notification::make()
                        ->title('Failed to Clear Conditions')
                        ->body('An error occurred while clearing conditions: '.$e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * Create action for clearing conditions by type
     */
    public static function makeClearByType(): static
    {
        return static::make('clearConditionsByType')
            ->label('Clear by Type')
            ->icon(Heroicon::OutlinedFunnel)
            ->color('warning')
            ->modalHeading('Clear Conditions by Type')
            ->modalDescription('Select the type of conditions to remove from this cart.')
            ->schema([
                \Filament\Forms\Components\Select::make('type')
                    ->label('Condition Type')
                    ->options([
                        'discount' => 'Discounts',
                        'tax' => 'Taxes',
                        'fee' => 'Fees',
                        'shipping' => 'Shipping',
                        'surcharge' => 'Surcharges',
                        'credit' => 'Credits',
                        'adjustment' => 'Adjustments',
                    ])
                    ->required()
                    ->native(false)
                    ->helperText('All conditions of this type will be removed'),
            ])
            ->action(function (array $data, $record, $livewire): void {
                // Get the cart record - either directly or from relation manager
                $cart = $record instanceof CartModel ? $record : $livewire->getOwnerRecord();

                try {
                    // Get the cart instance
                    $cartInstance = app(CartInstanceManager::class)
                        ->resolve($cart->instance, $cart->identifier);

                    // Remove conditions by type
                    $cartInstance->removeConditionsByType($data['type']);

                    Notification::make()
                        ->title('Conditions Cleared')
                        ->body("All '{$data['type']}' conditions have been removed from the cart.")
                        ->success()
                        ->send();

                } catch (Exception $e) {
                    Notification::make()
                        ->title('Failed to Clear Conditions')
                        ->body('An error occurred while clearing conditions: '.$e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
