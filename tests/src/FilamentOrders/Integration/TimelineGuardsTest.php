<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentOrders\Pages\OrderTimelinePage;
use AIArmada\FilamentOrders\Widgets\OrderTimelineWidget;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

uses(TestCase::class);

afterEach(function (): void {
    Mockery::close();
});

it('keeps timeline pagination enabled', function (): void {
    $page = app(OrderTimelinePage::class);
    $livewire = Mockery::mock(HasTable::class);

    $table = $page->table(Table::make($livewire));

    expect($table->isPaginated())->toBeTrue();
});

it('rejects tampered or oversized note input server-side', function (): void {
    expect(OrderTimelineWidget::normalizeNoteInput([
        'content' => 'Packed and ready.',
        'visibility' => 'internal',
    ]))->toBe(['content' => 'Packed and ready.', 'visibility' => 'internal']);

    expect(OrderTimelineWidget::normalizeNoteInput([
        'content' => 'Visible to customer.',
        'visibility' => 'customer',
    ])['visibility'])->toBe('customer');

    expect(OrderTimelineWidget::normalizeNoteInput([
        'content' => 'Tampered.',
        'visibility' => 'admin-only',
    ]))->toBeNull();

    expect(OrderTimelineWidget::normalizeNoteInput([
        'content' => str_repeat('a', 2001),
        'visibility' => 'internal',
    ]))->toBeNull();

    expect(OrderTimelineWidget::normalizeNoteInput([
        'content' => '   ',
        'visibility' => 'internal',
    ]))->toBeNull();

    expect(OrderTimelineWidget::normalizeNoteInput([]))->toBeNull();
});
