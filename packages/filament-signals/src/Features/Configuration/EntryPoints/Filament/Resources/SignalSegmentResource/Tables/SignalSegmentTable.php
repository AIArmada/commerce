<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalSegmentResource\Tables;

use AIArmada\Signals\Features\Reporting\Api\SegmentAdministration;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalSegmentResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Throwable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class SignalSegmentTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Segment name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Internal key')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('match_type')
                    ->label('Rule match')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'all' => 'Every rule (AND)',
                        'any' => 'Any rule (OR)',
                        default => str($state)->headline()->toString(),
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('conditions')
                    ->label('Rules')
                    ->formatStateUsing(fn (mixed $state): string => (string) count(is_array($state) ? $state : [])),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No audience segments yet')
            ->emptyStateDescription('Create your first reusable audience group to filter reports and compare visitor behavior.')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
                SelectFilter::make('match_type')
                    ->label('Rule match')
                    ->options([
                        'all' => 'Every rule (AND)',
                        'any' => 'Any rule (OR)',
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->label('Edit'),
                DeleteAction::make()->using(static fn (Model $record): bool => app(SegmentAdministration::class)->delete((string) $record->getKey()))
                    ->label('Delete')
                    ->modalHeading('Delete audience segment?')
                    ->modalDescription('This will remove the saved audience segment from reports and filters.')
                    ->successNotificationTitle('Audience segment deleted'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->using(static function (DeleteBulkAction $action, Collection | LazyCollection $records): void {
                        foreach ($records as $record) {
                            try {
                                if (! SignalSegmentResource::canDelete($record) || ! app(SegmentAdministration::class)->delete((string) $record->getKey())) {
                                    $action->reportBulkProcessingFailure();
                                }
                            } catch (Throwable $exception) {
                                $action->reportBulkProcessingFailure();
                                report($exception);
                            }
                        }
                    })
                        ->label('Delete selected')
                        ->modalHeading('Delete audience segments?')
                        ->modalDescription('This will remove the selected saved audience segments from reports and filters.')
                        ->successNotificationTitle('Audience segments deleted'),
                ]),
            ]);
    }
}
