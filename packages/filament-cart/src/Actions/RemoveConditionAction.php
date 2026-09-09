<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Actions;

use AIArmada\Cart\Actions\RemoveStoredConditions;
use AIArmada\Cart\Snapshots\CartSnapshot;
use AIArmada\Cart\Snapshots\CartSnapshotCondition as CartCondition;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

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
                    $removed = app(RemoveStoredConditions::class)->removeSnapshotCondition($record);

                    if (! $removed) {
                        throw new Exception('Condition not found or could not be removed');
                    }

                    Notification::make()
                        ->title('Condition Removed')
                        ->body("The '{$record->name}' condition has been removed.")
                        ->success()
                        ->send();

                } catch (Exception $e) {
                    Notification::make()
                        ->title('Failed to Remove Condition')
                        ->body('An error occurred while removing the condition: ' . $e->getMessage())
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
                $cart = self::resolveCartRecord($record, $livewire);

                try {
                    app(RemoveStoredConditions::class)->clearAll($cart);

                    Notification::make()
                        ->title('All Conditions Cleared')
                        ->body('All conditions have been removed from the cart.')
                        ->success()
                        ->send();

                } catch (Exception $e) {
                    Notification::make()
                        ->title('Failed to Clear Conditions')
                        ->body('An error occurred while clearing conditions: ' . $e->getMessage())
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
                Select::make('type')
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
                $cart = self::resolveCartRecord($record, $livewire);

                try {
                    app(RemoveStoredConditions::class)->clearByType($cart, (string) $data['type']);

                    Notification::make()
                        ->title('Conditions Cleared')
                        ->body("All '{$data['type']}' conditions have been removed from the cart.")
                        ->success()
                        ->send();

                } catch (Exception $e) {
                    Notification::make()
                        ->title('Failed to Clear Conditions')
                        ->body('An error occurred while clearing conditions: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    private static function resolveCartRecord(mixed $record, mixed $livewire = null): CartSnapshot
    {
        $cart = $record instanceof CartSnapshot ? $record : null;

        if ($cart === null && is_object($livewire) && method_exists($livewire, 'getOwnerRecord')) {
            $ownerRecord = $livewire->getOwnerRecord();
            $cart = $ownerRecord instanceof CartSnapshot ? $ownerRecord : null;
        }

        if (! $cart instanceof CartSnapshot) {
            throw new InvalidArgumentException('Cart actions require a cart snapshot record.');
        }

        if (! CartSnapshot::ownerScopingEnabled()) {
            return $cart;
        }

        /** @var CartSnapshot $validated */
        $validated = OwnerWriteGuard::findOrFailForOwner(
            CartSnapshot::class,
            (string) $cart->getKey(),
            includeGlobal: false,
            message: 'Cart is not accessible in the current owner scope.',
        );

        return $validated;
    }
}
