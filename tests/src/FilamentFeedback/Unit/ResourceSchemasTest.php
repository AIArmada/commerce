<?php

declare(strict_types=1);

use AIArmada\FilamentFeedback\Resources\FeedbackResponseResource;
use AIArmada\Commerce\Tests\TestCase;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

uses(TestCase::class);

it('builds the feedback response submitted_at date range filter', function (): void {
    $table = FeedbackResponseResource::table(Table::make(Mockery::mock(HasTable::class)));

    $submittedAtFilter = collect($table->getFilters())
        ->first(fn ($filter): bool => $filter instanceof Filter && $filter->getName() === 'submitted_at');

    expect($submittedAtFilter)->toBeInstanceOf(Filter::class);

    $components = $submittedAtFilter->getFormSchema();

    expect($components)->toHaveCount(2)
        ->and($components[0])->toBeInstanceOf(DatePicker::class)
        ->and($components[1])->toBeInstanceOf(DatePicker::class);
});
