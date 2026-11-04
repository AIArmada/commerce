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
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
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
                    ->formatStateUsing(function ($record, Table $table) use (&$parentMap): string {
                        $parentMap ??= self::loadAncestorMap(self::currentPageCategoryIds($table));
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
     * @return list<string>
     */
    private static function currentPageCategoryIds(Table $table): array
    {
        $records = $table->getRecords();

        if ($records instanceof Paginator || $records instanceof CursorPaginator) {
            $records = $records->items();
        }

        $ids = [];

        foreach ($records as $record) {
            if ($record instanceof Category) {
                $ids[] = (string) $record->getKey();
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Load only the ancestors of the categories on the current page.
     *
     * Each query resolves one level of the ancestor chain, so the loaded row
     * count is bounded by the visible page rather than the whole category tree.
     * The loop terminates when no new parents are found or when a cycle is
     * encountered, because every queued ID is marked as visited.
     *
     * @param  list<string>  $categoryIds
     * @return array<string, string|null>
     */
    private static function loadAncestorMap(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $owner = OwnerContext::resolve();
        $includeGlobal = (bool) config('products.features.owner.include_global', false);

        /** @var array<string, bool> $visited */
        $visited = [];
        $pending = [];

        foreach ($categoryIds as $categoryId) {
            if (! isset($visited[$categoryId])) {
                $visited[$categoryId] = true;
                $pending[] = $categoryId;
            }
        }

        /** @var array<string, string|null> $map */
        $map = [];

        while ($pending !== []) {
            $next = [];

            foreach (array_chunk($pending, 500) as $chunk) {
                $categories = OwnerQuery::applyToEloquentBuilder(
                    Category::query()->select(['id', 'parent_id']),
                    $owner,
                    $includeGlobal,
                )
                    ->whereIn('id', $chunk)
                    ->get();

                foreach ($categories as $category) {
                    $categoryId = (string) $category->getKey();
                    $parentId = $category->parent_id === null ? null : (string) $category->parent_id;

                    $map[$categoryId] = $parentId;

                    if ($parentId !== null && ! isset($visited[$parentId])) {
                        $visited[$parentId] = true;
                        $next[] = $parentId;
                    }
                }
            }

            $pending = $next;
        }

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
