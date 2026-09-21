<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

uses(TestCase::class);

// ============================================
// ReturnAuthorizationResource Tests
// ============================================

it('has standard CRUD pages', function (): void {
    $pages = ReturnAuthorizationResource::getPages();

    expect($pages)->toHaveKey('index');
    expect($pages)->toHaveKey('create');
    expect($pages)->toHaveKey('view');
    expect($pages)->toHaveKey('edit');
});

it('builds return authorization resource form schema', function (): void {
    $schema = ReturnAuthorizationResource::form(Schema::make());

    expect($schema->getComponents())->not()->toBeEmpty();
});

it('builds return authorization resource table definition', function (): void {
    $livewire = Mockery::mock(HasTable::class);

    $table = ReturnAuthorizationResource::table(Table::make($livewire));

    expect($table->getColumns())->not()->toBeEmpty();
    expect($table->getRecordActions())->not()->toBeEmpty();
});
