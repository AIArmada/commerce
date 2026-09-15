<?php

declare(strict_types=1);

use AIArmada\FilamentCashierChip\Resources\BaseCashierChipResource;
use AIArmada\FilamentCashierChip\Resources\InvoiceResource;
use AIArmada\FilamentCashierChip\Resources\InvoiceResource\Pages\ListInvoices;

it('has the expected invoice resource structure', function (): void {
    $reflection = new ReflectionClass(InvoiceResource::class);

    expect(is_subclass_of(InvoiceResource::class, BaseCashierChipResource::class))->toBeTrue()
        ->and($reflection->hasProperty('modelLabel'))->toBeTrue()
        ->and($reflection->hasProperty('pluralModelLabel'))->toBeTrue()
        ->and($reflection->hasMethod('getModel'))->toBeTrue()
        ->and($reflection->hasMethod('getGloballySearchableAttributes'))->toBeTrue()
        ->and($reflection->hasMethod('getPages'))->toBeTrue()
        ->and($reflection->hasMethod('table'))->toBeTrue()
        ->and($reflection->hasMethod('infolist'))->toBeTrue();
});

it('does not hardcode admin dashboard route in invoice list page', function (): void {
    $listInvoicesPath = (new ReflectionClass(ListInvoices::class))->getFileName();

    expect($listInvoicesPath)->not->toBeFalse();

    if (! is_string($listInvoicesPath)) {
        return;
    }

    $source = file_get_contents($listInvoicesPath);

    expect($source)->toBeString();
    expect($source)->not->toContain('filament.admin.pages.billing-dashboard');
    expect($source)->toContain('pages.cashier-chip-dashboard');
});
