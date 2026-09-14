<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\CategoryResource\Tables;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\FilamentProducts\Resources\CategoryResource;
use AIArmada\Products\Models\Category;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        $parentMap = null;

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Category')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($record) use (&$parentMap) {
                        $parentMap ??= self::loadParentMap();
                        $depth = self::depthFromMap((string) $record->getKey(), $parentMap);
                        $prefix = str_repeat('— ', $depth);

                        return $prefix . $record->name;
                    })
                    ->description(fn ($record) => $record->slug),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('Root')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),

                Tables\Columns\TextColumn::make('position')
                    ->label('Position')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('position')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'hidden' => 'Hidden',
                        'archived' => 'Archived',
                    ]),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),

                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Parent')
                    ->relationship(
                        'parent',
                        'name',
                        modifyQueryUsing: function (Builder $query): Builder {
                            $owner = OwnerContext::resolve();

                            return OwnerQuery::applyToEloquentBuilder($query, $owner);
                        }
                    )
                    ->placeholder('All')
                    ->options(function (): array {
                        $owner = OwnerContext::resolve();
                        $categories = OwnerQuery::applyToEloquentBuilder(Category::query(), $owner)->whereNull('parent_id')->pluck('name', 'id')->toArray();

                        return ['0' => 'Root Categories'] + $categories;
                    }),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('add_child')
                    ->label('Add Child')
                    ->icon('heroicon-o-plus')
                    ->url(fn ($record) => CategoryResource::getUrl('create', ['parent' => $record->id])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize(fn (): bool => FilamentPermission::hasAbility('category.delete')),
                    BulkAction::make('show')
                        ->label('Set Active')
                        ->icon('heroicon-o-eye')
                        ->authorize(fn (): bool => FilamentPermission::hasAbility('category.update'))
                        ->action(function (Collection $records): void {
                            $records->each(function (Category $record): void {
                                $record->update(['status' => 'active', 'hidden_at' => null]);
                            });
                        }),
                    BulkAction::make('hide')
                        ->label('Set Hidden')
                        ->icon('heroicon-o-eye-slash')
                        ->authorize(fn (): bool => FilamentPermission::hasAbility('category.update'))
                        ->action(function (Collection $records): void {
                            $records->each(function (Category $record): void {
                                $record->update(['status' => 'hidden', 'hidden_at' => CarbonImmutable::now()]);
                            });
                        }),
                ]),
            ]);
    }

    /**
     * @return array<string, string|null>
     */
    private static function loadParentMap(): array
    {
        $owner = OwnerContext::resolve();

        /** @var array<string, string|null> $map */
        $map = OwnerQuery::applyToEloquentBuilder(
            Category::query()->select(['id', 'parent_id']),
            $owner,
            (bool) config('products.features.owner.include_global', false)
        )
            ->pluck('parent_id', 'id')
            ->map(static fn (mixed $parentId): ?string => $parentId === null ? null : (string) $parentId)
            ->all();

        return $map;
    }

    /**
     * @param  array<string, string|null>  $map
     */
    private static function depthFromMap(string $id, array $map): int
    {
        $depth = 0;
        $visited = [$id];
        $current = $map[$id] ?? null;

        while ($current !== null) {
            if (in_array($current, $visited, true)) {
                return $depth;
            }

            $visited[] = $current;
            $depth++;
            $current = $map[$current] ?? null;
        }

        return $depth;
    }
}
