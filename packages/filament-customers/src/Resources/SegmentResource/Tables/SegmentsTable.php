<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources\SegmentResource\Tables;

use AIArmada\Customers\Enums\SegmentType;
use AIArmada\Customers\Features\Segmentation\Actions\RebuildAllSegments;
use AIArmada\Customers\Features\Segmentation\Actions\SaveSegment;
use AIArmada\Customers\Features\Segmentation\Models\Segment;
use AIArmada\FilamentCustomers\Resources\SegmentResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\LazyCollection;
use Throwable;

final class SegmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Segment')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->description),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),

                TextColumn::make('customers_count')
                    ->label('Customers')
                    ->counts('customers')
                    ->sortable()
                    ->alignEnd(),

                IconColumn::make('is_automatic')
                    ->label('Auto')
                    ->boolean(),

                TextColumn::make('deactivated_at')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === null ? 'Active' : 'Deactivated')
                    ->color(fn ($state) => $state === null ? 'success' : 'danger'),

                TextColumn::make('priority')
                    ->label('Priority')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(
                        collect(SegmentType::cases())
                            ->mapWithKeys(fn ($type) => [$type->value => $type->label()])
                    ),

                TernaryFilter::make('is_automatic')
                    ->label('Assignment Type')
                    ->trueLabel('Automatic')
                    ->falseLabel('Manual'),

                TernaryFilter::make('deactivated_at')
                    ->label('Status')
                    ->trueLabel('Active')
                    ->falseLabel('Deactivated')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('deactivated_at'),
                        false: fn (Builder $query) => $query->whereNotNull('deactivated_at'),
                    ),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('rebuild')
                    ->label('Rebuild')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Segment $record): bool => $record->is_automatic && $record->isActive())
                    ->requiresConfirmation()
                    ->action(function (Segment $record): void {
                        $user = Filament::auth()->user();
                        abort_unless($user !== null, 403);

                        Gate::forUser($user)->authorize('viewAny', Segment::class);
                        Gate::forUser($user)->authorize('rebuild', $record);

                        $count = app(RebuildAllSegments::class)->rebuildSegment($record);

                        Notification::make()
                            ->success()
                            ->title('Segment Rebuilt')
                            ->body("{$count} customer(s) now in this segment.")
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->using(static function (DeleteBulkAction $action, Collection | LazyCollection $records): void {
                        foreach ($records as $record) {
                            try {
                                if (! $record instanceof Segment || ! SegmentResource::canDelete($record) || ! app(SaveSegment::class)->delete($record)) {
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
