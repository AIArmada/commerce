<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalGoalResource\Tables;

use AIArmada\Signals\Features\Reporting\Api\GoalAdministration;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalGoalResource;
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

final class SignalGoalTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Goal name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('goal_type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_name')
                    ->label('Event name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('event_category')
                    ->label('Event category')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tracked_property_name')
                    ->label('Website / app')
                    ->toggleable(),
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
            ->emptyStateHeading('No goals yet')
            ->emptyStateDescription('Create your first success event definition so dashboards and funnels can measure it.')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
                SelectFilter::make('goal_type')
                    ->label('Type')
                    ->options(function (): array {
                        $options = [
                            'conversion' => 'Conversion',
                            'engagement' => 'Engagement',
                        ];
                        if (config('signals.features.monetary.enabled', true)) {
                            $options['revenue'] = 'Revenue';
                        }

                        return $options;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->label('Edit'),
                DeleteAction::make()->using(static fn (Model $record): bool => app(GoalAdministration::class)->delete((string) $record->getKey()))
                    ->label('Delete')
                    ->modalHeading('Delete goal?')
                    ->modalDescription('This will remove the saved goal from dashboards, funnels, and reports.')
                    ->successNotificationTitle('Goal deleted'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->using(static function (DeleteBulkAction $action, Collection | LazyCollection $records): void {
                        foreach ($records as $record) {
                            try {
                                if (! SignalGoalResource::canDelete($record) || ! app(GoalAdministration::class)->delete((string) $record->getKey())) {
                                    $action->reportBulkProcessingFailure();
                                }
                            } catch (Throwable $exception) {
                                $action->reportBulkProcessingFailure();
                                report($exception);
                            }
                        }
                    })
                        ->label('Delete selected')
                        ->modalHeading('Delete goals?')
                        ->modalDescription('This will remove the selected saved goals from dashboards, funnels, and reports.')
                        ->successNotificationTitle('Goals deleted'),
                ]),
            ]);
    }
}
