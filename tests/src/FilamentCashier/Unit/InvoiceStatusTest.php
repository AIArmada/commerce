<?php

declare(strict_types=1);

use AIArmada\FilamentCashier\Support\InvoiceStatus;

it('has all expected status cases', function (): void {
    $cases = InvoiceStatus::cases();

    expect($cases)->toHaveCount(5)
        ->and(collect($cases)->pluck('value')->toArray())->toContain(
            'paid',
            'open',
            'draft',
            'void',
            'uncollectible'
        );
});

it('provides label for each status', function (InvoiceStatus $status): void {
    expect($status->label())->toBeString()->not->toBeEmpty();
})->with([
    'paid' => [InvoiceStatus::Paid],
    'open' => [InvoiceStatus::Open],
    'draft' => [InvoiceStatus::Draft],
    'void' => [InvoiceStatus::Void],
    'uncollectible' => [InvoiceStatus::Uncollectible],
]);

it('provides color for each status', function (InvoiceStatus $status): void {
    expect($status->color())->toBeString()->not->toBeEmpty();
})->with([
    'paid' => [InvoiceStatus::Paid],
    'open' => [InvoiceStatus::Open],
    'draft' => [InvoiceStatus::Draft],
    'void' => [InvoiceStatus::Void],
    'uncollectible' => [InvoiceStatus::Uncollectible],
]);

it('provides icon for each status', function (InvoiceStatus $status): void {
    expect($status->icon())->toBeString()->toContain('heroicon');
})->with([
    'paid' => [InvoiceStatus::Paid],
    'open' => [InvoiceStatus::Open],
    'draft' => [InvoiceStatus::Draft],
    'void' => [InvoiceStatus::Void],
    'uncollectible' => [InvoiceStatus::Uncollectible],
]);

it('returns correct color for paid status', function (): void {
    expect(InvoiceStatus::Paid->color())->toBe('success');
});

it('returns correct color for open status', function (): void {
    expect(InvoiceStatus::Open->color())->toBe('warning');
});

it('returns correct color for draft status', function (): void {
    expect(InvoiceStatus::Draft->color())->toBe('gray');
});

it('returns correct color for void status', function (): void {
    expect(InvoiceStatus::Void->color())->toBe('danger');
});

it('returns correct color for uncollectible status', function (): void {
    expect(InvoiceStatus::Uncollectible->color())->toBe('danger');
});

it('returns correct icon for paid status', function (): void {
    expect(InvoiceStatus::Paid->icon())->toBe('heroicon-o-check-circle');
});

it('returns correct icon for open status', function (): void {
    expect(InvoiceStatus::Open->icon())->toBe('heroicon-o-clock');
});

it('returns correct icon for draft status', function (): void {
    expect(InvoiceStatus::Draft->icon())->toBe('heroicon-o-pencil');
});
