<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\RelationManagers\CommissionPromotionsRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\RelationManagers\CommissionRulesRelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

it('CommissionRulesRelationManager configures mutation-capable form and table contracts', function (): void {
    $form = Mockery::mock(Schema::class);
    $form->shouldReceive('schema')->once()->andReturnSelf();

    $table = Mockery::mock(Table::class);
    $table->shouldReceive('recordTitleAttribute')->once()->andReturnSelf();
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('headerActions')->once()->andReturnSelf();
    $table->shouldReceive('actions')->once()->andReturnSelf();
    $table->shouldReceive('bulkActions')->once()->andReturnSelf();

    $relationManager = new CommissionRulesRelationManager;

    $relationManager->form($form);
    $relationManager->table($table);

    expect(true)->toBeTrue();
});

it('CommissionPromotionsRelationManager configures mutation-capable form and table contracts', function (): void {
    $form = Mockery::mock(Schema::class);
    $form->shouldReceive('schema')->once()->andReturnSelf();

    $table = Mockery::mock(Table::class);
    $table->shouldReceive('recordTitleAttribute')->once()->andReturnSelf();
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('headerActions')->once()->andReturnSelf();
    $table->shouldReceive('actions')->once()->andReturnSelf();
    $table->shouldReceive('bulkActions')->once()->andReturnSelf();

    $relationManager = new CommissionPromotionsRelationManager;

    $relationManager->form($form);
    $relationManager->table($table);

    expect(true)->toBeTrue();
});
