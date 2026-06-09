<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources\PromotionResource\Tables;

use AIArmada\FilamentPricing\Resources\PromotionResource;
use AIArmada\Promotions\Enums\PromotionType;
use AIArmada\Promotions\Models\Promotion;
use Filament\Actions;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Promotion')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->code),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),

                TextColumn::make('discount_value')
                    ->label('Discount')
                    ->formatStateUsing(fn ($state, $record) => $record->type->formatValue($state)),

                TextColumn::make('usage_count')
                    ->label('Uses')
                    ->numeric()
                    ->alignEnd()
                    ->formatStateUsing(
                        fn ($state, $record) => $record->usage_limit
                        ? "{$state}/{$record->usage_limit}"
                        : $state
                    ),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('d M Y')
                    ->placeholder('Always'),

                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime('d M Y')
                    ->placeholder('Never'),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(
                        collect(PromotionType::cases())
                            ->mapWithKeys(fn ($type) => [$type->value => $type->label()])
                    ),

                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->authorize(fn (): bool => PromotionResource::canCreate())
                    ->action(function (Promotion $record) {
                        $new = $record->replicate();
                        $new->name = $record->name . ' (Copy)';
                        $new->code = null;
                        $new->usage_count = 0;
                        $new->save();

                        return redirect(PromotionResource::getUrl('edit', ['record' => $new]));
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
