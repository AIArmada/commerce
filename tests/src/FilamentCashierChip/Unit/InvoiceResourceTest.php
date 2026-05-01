<?php

declare(strict_types=1);

use AIArmada\FilamentCashierChip\Resources\BaseCashierChipResource;
use AIArmada\FilamentCashierChip\Resources\InvoiceResource;
use AIArmada\FilamentCashierChip\Resources\InvoiceResource\Pages\ListInvoices;

it('extends base cashier chip resource', function (): void {
    expect(is_subclass_of(InvoiceResource::class, BaseCashierChipResource::class))->toBeTrue();
});

it('has model label property', function (): void {
    $reflection = new ReflectionClass(InvoiceResource::class);

    expect($reflection->hasProperty('modelLabel'))->toBeTrue();
});

it('has plural model label property', function (): void {
    $reflection = new ReflectionClass(InvoiceResource::class);

    expect($reflection->hasProperty('pluralModelLabel'))->toBeTrue();
});

it('has get model method', function (): void {
    $reflection = new ReflectionClass(InvoiceResource::class);

    expect($reflection->hasMethod('getModel'))->toBeTrue();
});

it('has globally searchable attributes method', function (): void {
    $reflection = new ReflectionClass(InvoiceResource::class);

    expect($reflection->hasMethod('getGloballySearchableAttributes'))->toBeTrue();
});

it('has pages method', function (): void {
    $reflection = new ReflectionClass(InvoiceResource::class);

    expect($reflection->hasMethod('getPages'))->toBeTrue();
});

it('has table method', function (): void {
    $reflection = new ReflectionClass(InvoiceResource::class);

    expect($reflection->hasMethod('table'))->toBeTrue();
});

it('has infolist method', function (): void {
    $reflection = new ReflectionClass(InvoiceResource::class);

    expect($reflection->hasMethod('infolist'))->toBeTrue();
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
