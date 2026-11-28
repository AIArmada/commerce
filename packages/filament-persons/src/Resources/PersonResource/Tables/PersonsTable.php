<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\PersonResource\Tables;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentPersons\Resources\PersonResource;
use AIArmada\Persons\Features\Identity\Actions\DeletePersonAction;
use AIArmada\Persons\Models\Person;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Throwable;

class PersonsTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with('titleAssignments.title.category'),
            )
            ->columns([
                TextColumn::make('name')
                    ->searchable(['searchable_name', 'name', 'family_name', 'middle_name'])
                    ->sortable(),
                TextColumn::make('family_name')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('gender')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                self::featuredColumn(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                self::featuredFilter(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->using(static function (DeleteBulkAction $action, Collection | LazyCollection $records): void {
                        foreach ($records as $record) {
                            try {
                                if (! $record instanceof Person || ! PersonResource::canDelete($record) || ! app(DeletePersonAction::class)->execute($record)) {
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

        return self::applyFeaturedOrdering($table);
    }

    public static function featuredColumn(): IconColumn
    {
        return IconColumn::make('is_featured')
            ->label(__('Featured'))
            ->boolean()
            ->sortable();
    }

    public static function featuredFilter(): TernaryFilter
    {
        return TernaryFilter::make('is_featured')
            ->label(__('Featured'));
    }

    public static function applyFeaturedOrdering(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query, HasTable $livewire): Builder => $query->when($livewire->isTableReordering(), fn (Builder $query): Builder => $query->where('is_featured', true)))
            ->reorderable('order_column')
            ->authorizeReorder(fn (): bool => FilamentPermission::hasAbility('person.update'))
            ->defaultSort('order_column');
    }
}
