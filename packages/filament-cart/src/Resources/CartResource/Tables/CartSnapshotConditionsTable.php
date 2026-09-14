<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\CartResource\Tables;

use AIArmada\Cart\Actions\RemoveStoredConditions;
use AIArmada\Cart\Snapshots\CartSnapshotCondition;
use AIArmada\FilamentCart\Actions\RemoveConditionAction;
use AIArmada\FilamentCart\Support\ConditionTargetLabels;
use Exception;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

final class CartSnapshotConditionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Condition Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'discount' => 'success',
                        'tax', 'fee', 'surcharge' => 'warning',
                        'shipping' => 'info',
                        'credit' => 'primary',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('target')
                    ->label('Target')
                    ->formatStateUsing(fn (?string $state): string => ConditionTargetLabels::label($state))
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('value')
                    ->label('Value')
                    ->alignEnd()
                    ->badge()
                    ->color(fn (?string $state): string => is_string($state) && str_contains($state, '%') ? 'info' : 'secondary')
                    ->sortable(),

                TextColumn::make('operator')
                    ->label('Operator')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        '+' => 'success',
                        '-' => 'danger',
                        '*' => 'info',
                        '/' => 'warning',
                        '%' => 'primary',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_charge')
                    ->label('Charge')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_discount')
                    ->label('Discount')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_percentage')
                    ->label('Percentage')
                    ->boolean()
                    ->trueIcon('heroicon-o-percent-badge')
                    ->falseIcon('heroicon-o-currency-dollar')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_dynamic')
                    ->label('Dynamic')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_global')
                    ->label('Global')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedGlobeAsiaAustralia)
                    ->falseIcon(Heroicon::OutlinedMinusCircle)
                    ->toggleable(),

                TextColumn::make('parsed_value')
                    ->label('Parsed Value')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('order')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('cart_item_id')
                    ->label('Item')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'discount' => 'Discount',
                        'fee' => 'Fee',
                        'tax' => 'Tax',
                        'shipping' => 'Shipping',
                        'surcharge' => 'Surcharge',
                        'credit' => 'Credit',
                        'adjustment' => 'Adjustment',
                    ]),

                SelectFilter::make('target')
                    ->options(ConditionTargetLabels::options()),
            ])
            ->recordActions([
                RemoveConditionAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('removeSelected')
                        ->label('Remove Selected')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            /** @var Collection<int|string, CartSnapshotCondition> $records */
                            $removed = 0;
                            $failed = 0;

                            foreach ($records->chunk(100) as $chunk) {
                                /** @var CartSnapshotCondition $record */
                                foreach ($chunk as $record) {
                                    try {
                                        if (app(RemoveStoredConditions::class)->removeSnapshotCondition($record)) {
                                            $removed++;
                                        } else {
                                            $failed++;
                                        }
                                    } catch (Exception) {
                                        $failed++;
                                    }
                                }
                            }

                            if ($failed > 0) {
                                Notification::make()
                                    ->title('Some conditions could not be removed')
                                    ->body("Removed {$removed}, failed {$failed}.")
                                    ->warning()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('Conditions Removed')
                                ->body("Removed {$removed} " . str('condition')->plural($removed) . '.')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('order')
            ->poll('30s');
    }
}
