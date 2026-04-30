<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Resources\PromotionResource\Tables;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentPromotions\Enums\PromotionType;
use AIArmada\FilamentPromotions\Models\Promotion;
use AIArmada\Promotions\Support\PromotionsOwnerScope;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

final class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('primary')
                    ->placeholder('Auto')
                    ->searchable(),

                TextColumn::make('type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('discount_value')
                    ->label('Discount')
                    ->formatStateUsing(function (Promotion $record): string {
                        if ($record->type->value === 'percentage') {
                            return $record->discount_value . '%';
                        }

                        return '$' . number_format($record->discount_value / 100, 2);
                    })
                    ->sortable(),

                TextColumn::make('usage_count')
                    ->label('Uses')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_stackable')
                    ->label('Stack')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('starts_at')
                    ->label('Start')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ends_at')
                    ->label('End')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('priority')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(PromotionType::class)
                    ->native(false),

                TernaryFilter::make('is_active')
                    ->label('Active'),

                TernaryFilter::make('is_stackable')
                    ->label('Stackable'),

                TernaryFilter::make('has_code')
                    ->label('Has Promo Code')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('code'),
                        false: fn ($query) => $query->whereNull('code'),
                    ),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (Promotion $record): void {
                        if (! PromotionsOwnerScope::isEnabled()) {
                            return;
                        }

                        OwnerWriteGuard::findOrFailForOwner(
                            Promotion::class,
                            (string) $record->getKey(),
                            PromotionsOwnerScope::resolveOwner(),
                            includeGlobal: false,
                            message: 'Promotion is not accessible in the current owner scope.',
                        );
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (Collection $records): void {
                            if (! PromotionsOwnerScope::isEnabled()) {
                                return;
                            }

                            $owner = PromotionsOwnerScope::resolveOwner();

                            foreach ($records as $record) {
                                OwnerWriteGuard::findOrFailForOwner(
                                    Promotion::class,
                                    (string) $record->getKey(),
                                    $owner,
                                    includeGlobal: false,
                                    message: 'Promotion is not accessible in the current owner scope.',
                                );
                            }
                        }),
                ]),
            ]);
    }
}
