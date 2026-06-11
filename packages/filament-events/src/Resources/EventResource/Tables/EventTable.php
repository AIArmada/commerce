<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\Tables;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Enums\EventModerationStatus;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\EventVisibility;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventSeries;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class EventTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Event $record): string => $record->slug),

                TextColumn::make('series.name')
                    ->label('Series')
                    ->placeholder('No series')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (EventStatus $state): string => $state->label())
                    ->color(fn (EventStatus $state): string => $state->color()),

                TextColumn::make('moderation_status')
                    ->label('Moderation')
                    ->badge()
                    ->formatStateUsing(fn (EventModerationStatus $state): string => $state->label())
                    ->color(fn (EventModerationStatus $state): string => $state->color()),

                TextColumn::make('visibility')
                    ->badge()
                    ->formatStateUsing(fn (EventVisibility $state): string => $state->label())
                    ->color(fn (EventVisibility $state): string => $state->color()),

                TextColumn::make('occurrences_count')
                    ->label('Runs')
                    ->counts('occurrences')
                    ->sortable(),

                TextColumn::make('product.name')
                    ->label('Product')
                    ->placeholder('Not linked')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(EventStatus::options()),

                SelectFilter::make('moderation_status')
                    ->label('Moderation')
                    ->options(EventModerationStatus::options()),

                SelectFilter::make('visibility')
                    ->options(EventVisibility::options()),

                SelectFilter::make('event_series_id')
                    ->label('Series')
                    ->options(static fn (): array => OwnerUiScope::apply(EventSeries::query(), includeGlobal: false)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
