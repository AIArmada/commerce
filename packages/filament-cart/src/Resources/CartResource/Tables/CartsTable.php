<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\CartResource\Tables;

use AIArmada\Cart\Snapshots\CartInstanceManager;
use AIArmada\Cart\Snapshots\CartSnapshot as Cart;
use AIArmada\Cart\Support\CartMoney;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentCart\Resources\CartResource;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class CartsTable
{
    public static function resolvePollingInterval(): string
    {
        $interval = config('filament-cart.polling_interval', '30s');

        if (is_int($interval) || is_float($interval)) {
            return (string) $interval . 's';
        }

        if (! is_string($interval)) {
            return '30s';
        }

        return is_numeric($interval)
            ? $interval . 's'
            : $interval;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('identifier')
                    ->label('Cart ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->tooltip('Click to copy'),

                TextColumn::make('instance')
                    ->label('Instance')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'default' ? 'primary' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),

                TextColumn::make('items_count')
                    ->label('Items')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->alignEnd()
                    ->formatStateUsing(fn (int | string | null $state, Cart $record): string => CartMoney::formatMinor(
                        (int) $state,
                        $record->currency,
                    ))
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->alignEnd()
                    ->formatStateUsing(fn (int | string | null $state, Cart $record): string => CartMoney::formatMinor(
                        (int) $state,
                        $record->currency,
                    ))
                    ->sortable(),

                TextColumn::make('savings')
                    ->label('Savings')
                    ->alignEnd()
                    ->badge()
                    ->color('success')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn (int | string | null $state, Cart $record): string => CartMoney::formatMinor(
                        (int) $state,
                        $record->currency,
                    ))
                    ->sortable(),

                TextColumn::make('currency')
                    ->label('Currency')
                    ->badge()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('instance')
                    ->options(
                        fn () => Cart::query()->forOwner(includeGlobal: Cart::includeGlobalRecords())
                            ->select('instance')
                            ->distinct()
                            ->orderBy('instance')
                            ->pluck('instance', 'instance')
                            ->toArray()
                    )
                    ->multiple(),

                SelectFilter::make('currency')
                    ->options(
                        fn () => Cart::query()->forOwner(includeGlobal: Cart::includeGlobalRecords())
                            ->select('currency')
                            ->distinct()
                            ->orderBy('currency')
                            ->pluck('currency', 'currency')
                            ->toArray()
                    ),

                Filter::make('has_items')
                    ->label('Has Items')
                    ->query(fn (Builder $query): Builder => /** @phpstan-ignore method.notFound */ $query->notEmpty()),

                Filter::make('has_savings')
                    ->label('Has Savings')
                    ->query(fn (Builder $query): Builder => /** @phpstan-ignore method.notFound */ $query->withSavings()),

                Filter::make('high_quantity')
                    ->label('10+ Units')
                    ->query(fn (Builder $query): Builder => $query->where('quantity', '>=', 10)),

                Filter::make('recent')
                    ->label('Recent (7 days)')
                    ->query(fn (Builder $query): Builder => /** @phpstan-ignore method.notFound */ $query->recent()),

                Filter::make('created_today')
                    ->label('Created Today')
                    ->query(fn (Builder $query): Builder => $query->whereDate('created_at', today())),
            ])
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),

                ActionGroup::make([
                    Action::make('clear_cart')
                        ->label('Clear Cart')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Cart $record): void {
                            $cart = self::authorizeCart($record);

                            app(CartInstanceManager::class)
                                ->resolveForSnapshot($cart)
                                ->clear();
                        })
                        ->visible(fn (Cart $record): bool => $record->items_count > 0)
                        ->successNotificationTitle('Cart cleared'),

                    Action::make('view_items')
                        ->label('View Items')
                        ->icon(Heroicon::OutlinedListBullet)
                        ->url(fn (Cart $record) => CartResource::getUrl('view', ['record' => $record])),

                    DeleteAction::make()
                        ->icon(Heroicon::OutlinedXMark)
                        ->using(function (Cart $record): void {
                            $cart = self::authorizeCart($record);

                            app(CartInstanceManager::class)
                                ->resolveForSnapshot($cart)
                                ->destroy();
                        })
                        ->successNotificationTitle('Cart deleted'),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->tooltip('More actions'),
            ])
            ->toolbarActions([
                BulkAction::make('clear_selected')
                    ->label('Clear Selected Carts')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        /** @var Collection<int|string, Cart> $records */
                        self::runBulkOperation($records, 'clear');
                    }),

                BulkAction::make('delete_selected')
                    ->label('Delete Selected Carts')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        /** @var Collection<int|string, Cart> $records */
                        self::runBulkOperation($records, 'delete');
                    }),
            ])
            ->defaultSort('updated_at', 'desc')
            ->poll(fn (): string => self::resolvePollingInterval())
            ->striped();
    }

    /**
     * @param  Collection<int|string, Cart>  $records
     * @param  'clear'|'delete'  $operation
     */
    private static function runBulkOperation(Collection $records, string $operation): void
    {
        $processed = 0;
        $failed = 0;

        foreach ($records->chunk(100) as $chunk) {
            /** @var Cart $record */
            foreach ($chunk as $record) {
                try {
                    $cart = self::authorizeCart($record);
                    $instance = app(CartInstanceManager::class)->resolveForSnapshot($cart);

                    if ($operation === 'delete') {
                        $instance->destroy();
                    } else {
                        $instance->clear();
                    }

                    $processed++;
                } catch (Exception) {
                    $failed++;
                }
            }
        }

        $label = $operation === 'delete' ? 'deleted' : 'cleared';

        if ($failed > 0) {
            Notification::make()
                ->title("Some carts could not be {$label}")
                ->body("{$label}: {$processed}, failed: {$failed}.")
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title("Selected carts {$label}")
            ->body("{$processed} " . str('cart')->plural($processed) . " {$label}.")
            ->success()
            ->send();
    }

    private static function authorizeCart(Cart $cart): Cart
    {
        if (! Cart::ownerScopingEnabled()) {
            return $cart;
        }

        /** @var Cart $validated */
        $validated = OwnerWriteGuard::findOrFailForOwner(
            Cart::class,
            (string) $cart->getKey(),
            includeGlobal: false,
            message: 'Cart is not accessible in the current owner scope.',
        );

        return $validated;
    }
}
