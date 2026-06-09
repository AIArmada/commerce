<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalInteractionRuleResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class SignalInteractionRuleTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('trigger_type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_name')
                    ->label('Event')
                    ->searchable(),
                Tables\Columns\TextColumn::make('settings.scanner_confidence')
                    ->label('Confidence')
                    ->badge()
                    ->formatStateUsing(static fn (mixed $state): string => is_numeric($state) ? ((string) ((int) $state) . '%') : '—')
                    ->color(static function (mixed $state): string {
                        if (! is_numeric($state)) {
                            return 'gray';
                        }

                        $score = (int) $state;

                        return match (true) {
                            $score >= 85 => 'success',
                            $score >= 70 => 'warning',
                            default => 'gray',
                        };
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('selector')
                    ->label('Selector')
                    ->wrap()
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('page_pattern')
                    ->label('Path pattern')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('trackedProperty.name')
                    ->label('Website / app')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->paginated([10])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
                SelectFilter::make('trigger_type')
                    ->label('Interaction type')
                    ->options([
                        'click' => 'Click',
                        'accordion' => 'Accordion toggle',
                        'media' => 'Audio / Video',
                        'youtube' => 'YouTube embed click',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
