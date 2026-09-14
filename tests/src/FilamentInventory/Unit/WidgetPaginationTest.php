<?php

declare(strict_types=1);

use AIArmada\FilamentInventory\Widgets\BackordersWidget;
use AIArmada\FilamentInventory\Widgets\ExpiringBatchesWidget;
use AIArmada\FilamentInventory\Widgets\ReorderSuggestionsWidget;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

afterEach(function (): void {
    if (class_exists(Mockery::class)) {
        Mockery::close();
    }
});

it('paginates dashboard widgets instead of baking in a query limit', function (): void {
    foreach ([ExpiringBatchesWidget::class, BackordersWidget::class, ReorderSuggestionsWidget::class] as $widget) {
        $table = (new $widget)->table(Table::make(Mockery::mock(HasTable::class)));

        expect($table->getDefaultPaginationPageOption())->toBe(10);

        $query = $table->getQuery();

        expect($query->getQuery()->limit)->toBeNull();
    }
});
