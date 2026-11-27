<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\TrackedPropertyResource\Tables;

use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\TrackedPropertyResource;
use AIArmada\Signals\Features\Ingestion\Api\PropertyAdministration;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Throwable;

final class TrackedPropertyTable
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
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('write_key')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_active'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()->using(static fn (Model $record): bool => app(PropertyAdministration::class)->delete((string) $record->getKey())),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->using(static function (DeleteBulkAction $action, Collection | LazyCollection $records): void {
                        foreach ($records as $record) {
                            try {
                                if (! TrackedPropertyResource::canDelete($record) || ! app(PropertyAdministration::class)->delete((string) $record->getKey())) {
                                    $action->reportBulkProcessingFailure();
                                }
                            } catch (Throwable $exception) {
                                $action->reportBulkProcessingFailure();
                                report($exception);
                            }
                        }
                    }),
                ]),
            ]);
    }
}
