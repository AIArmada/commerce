<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Schemas\AffiliateConversionForm;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Schemas\AffiliateConversionInfolist;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Tables\AffiliateConversionsTable;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\Schemas\AffiliatePayoutInfolist;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\Tables\AffiliatePayoutsTable;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\Schemas\AffiliateForm;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\Schemas\AffiliateInfolist;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\Tables\AffiliatesTable;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

// AffiliateForm Tests
it('AffiliateForm configures schema with components', function (): void {
    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('components')->once()->andReturnSelf();

    AffiliateForm::configure($schema);

    expect(true)->toBeTrue();
});

// AffiliateInfolist Tests
it('AffiliateInfolist configures schema with components', function (): void {
    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('components')->once()->andReturnSelf();

    AffiliateInfolist::configure($schema);

    expect(true)->toBeTrue();
});

// AffiliatesTable Tests
it('AffiliatesTable configures table with columns and filters', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('filters')->once()->andReturnSelf();
    $table->shouldReceive('actions')->once()->andReturnSelf();
    $table->shouldReceive('bulkActions')->once()->andReturnSelf();

    AffiliatesTable::configure($table);

    expect(true)->toBeTrue();
});

// AffiliateConversionForm Tests
it('AffiliateConversionForm exists and is callable', function (): void {
    expect(class_exists(AffiliateConversionForm::class))->toBeTrue();
});

// AffiliateConversionInfolist Tests
it('AffiliateConversionInfolist exists and is callable', function (): void {
    expect(class_exists(AffiliateConversionInfolist::class))->toBeTrue();
});

// AffiliateConversionsTable Tests
it('AffiliateConversionsTable exists and is callable', function (): void {
    expect(class_exists(AffiliateConversionsTable::class))->toBeTrue();
});

// AffiliatePayoutInfolist Tests
it('AffiliatePayoutInfolist exists and is callable', function (): void {
    expect(class_exists(AffiliatePayoutInfolist::class))->toBeTrue();
});

// AffiliatePayoutsTable Tests
it('AffiliatePayoutsTable exists and is callable', function (): void {
    expect(class_exists(AffiliatePayoutsTable::class))->toBeTrue();
});

// Test configures through mocking
it('AffiliateConversionInfolist configures schema', function (): void {
    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('components')->once()->andReturnSelf();

    AffiliateConversionInfolist::configure($schema);

    expect(true)->toBeTrue();
});

it('AffiliatePayoutInfolist configures schema', function (): void {
    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('components')->once()->andReturnSelf();

    AffiliatePayoutInfolist::configure($schema);

    expect(true)->toBeTrue();
});

it('AffiliateConversionForm configures schema', function (): void {
    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('components')->once()->andReturnSelf();

    AffiliateConversionForm::configure($schema);

    expect(true)->toBeTrue();
});

it('AffiliateConversionsTable configures table', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('filters')->once()->andReturnSelf();
    $table->shouldReceive('actions')->once()->andReturnSelf();
    $table->shouldReceive('bulkActions')->once()->andReturnSelf();

    AffiliateConversionsTable::configure($table);

    expect(true)->toBeTrue();
});

it('conversion resource schemas use neutral reference fields', function (): void {
    $repositoryRoot = dirname(__DIR__, 4);

    $infolistSource = file_get_contents($repositoryRoot . '/packages/filament-affiliates/src/Resources/AffiliateConversionResource/Schemas/AffiliateConversionInfolist.php');
    $formSource = file_get_contents($repositoryRoot . '/packages/filament-affiliates/src/Resources/AffiliateConversionResource/Schemas/AffiliateConversionForm.php');
    $tableSource = file_get_contents($repositoryRoot . '/packages/filament-affiliates/src/Resources/AffiliateConversionResource/Tables/AffiliateConversionsTable.php');

    expect($infolistSource)
        ->toContain("TextEntry::make('external_reference')")
        ->toContain("TextEntry::make('subject_identifier')")
        ->toContain("Section::make('Cart Integration')")
        ->and($formSource)
        ->toContain("TextInput::make('external_reference')")
        ->toContain("TextInput::make('subject_identifier')")
        ->toContain("Section::make('Cart Integration')")
        ->and($tableSource)
        ->toContain("TextColumn::make('external_reference')")
        ->not->toContain("->label('Order / Ref')");
});

it('AffiliatePayoutsTable configures table', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('filters')->once()->andReturnSelf();
    $table->shouldReceive('actions')->once()->andReturnSelf();
    $table->shouldReceive('bulkActions')->once()->andReturnSelf();

    AffiliatePayoutsTable::configure($table);

    expect(true)->toBeTrue();
});

it('AffiliatePayoutsTable uses canonical payout status values', function (): void {
    $repositoryRoot = dirname(__DIR__, 4);
    $source = file_get_contents($repositoryRoot . '/packages/filament-affiliates/src/Resources/AffiliatePayoutResource/Tables/AffiliatePayoutsTable.php');

    expect($source)
        ->toContain('PayoutStatus::options()')
        ->toContain('CompletedPayout::value()')
        ->toContain('ProcessingPayout::value()')
        ->toContain('FailedPayout::value()')
        ->not->toContain("updateStatus(\$payout, 'paid')")
        ->not->toContain("updateStatus(\$payout, 'queued')");
});
