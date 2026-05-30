<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliates\Resources\AffiliateCommissionTemplateResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateFraudSignalResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateFraudSignalResource\Schemas\AffiliateFraudSignalInfolist;
use AIArmada\FilamentAffiliates\Resources\AffiliateLinkResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateNetworkResource;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\RelationManagers\PayoutEventsRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\RelationManagers\CommissionPromotionsRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\RelationManagers\CommissionRulesRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\RelationManagers\CreativesRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\RelationManagers\MembershipsRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\RelationManagers\TiersRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\Schemas\AffiliateProgramInfolist;
use AIArmada\FilamentAffiliates\Resources\AffiliateRankHistoryResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateRankResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers\PayoutHoldsRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers\PayoutMethodsRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers\PayoutsRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\Schemas\AffiliateInfolist;
use AIArmada\FilamentAffiliates\Resources\AffiliateSupportTicketResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateTaxDocumentResource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

it('AffiliateProgramResource configures form schema', function (): void {
    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('schema')->once()->andReturnSelf();

    AffiliateProgramResource::form($schema);

    expect(true)->toBeTrue();
});

it('AffiliateProgramResource configures table schema', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('filters')->once()->andReturnSelf();
    $table->shouldReceive('actions')->once()->andReturnSelf();
    $table->shouldReceive('bulkActions')->once()->andReturnSelf();

    AffiliateProgramResource::table($table);

    expect(true)->toBeTrue();
});

it('AffiliateProgramResource exposes infolist contract', function (): void {
    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('components')->once()->andReturnSelf();

    AffiliateProgramResource::infolist($schema);

    expect(true)->toBeTrue();
});

it('AffiliateProgramInfolist configures schema', function (): void {
    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('components')->once()->andReturnSelf();

    AffiliateProgramInfolist::configure($schema);

    expect(true)->toBeTrue();
});

it('AffiliateFraudSignalResource configures infolist schema', function (): void {
    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('components')->once()->andReturnSelf();

    AffiliateFraudSignalInfolist::configure($schema);

    expect(true)->toBeTrue();
});

it('AffiliateFraudSignalResource exposes infolist contract', function (): void {
    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('components')->once()->andReturnSelf();

    AffiliateFraudSignalResource::infolist($schema);

    expect(true)->toBeTrue();
});

it('AffiliateCommissionTemplateResource configures form schema', function (): void {
    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('schema')->once()->andReturnSelf();

    AffiliateCommissionTemplateResource::form($schema);

    expect(true)->toBeTrue();
});

it('AffiliateCommissionTemplateResource configures table schema', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('actions')->once()->andReturnSelf();
    $table->shouldReceive('bulkActions')->once()->andReturnSelf();

    AffiliateCommissionTemplateResource::table($table);

    expect(true)->toBeTrue();
});

it('AffiliateCommissionTemplateResource exposes infolist contract', function (): void {
    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('schema')->once()->andReturnSelf();

    AffiliateCommissionTemplateResource::infolist($schema);

    expect(true)->toBeTrue();
});

it('AffiliateRankHistoryResource configures table schema', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('actions')->once()->andReturnSelf();

    AffiliateRankHistoryResource::table($table);

    expect(true)->toBeTrue();
});

it('AffiliateRankHistoryResource exposes infolist contract', function (): void {
    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('schema')->once()->andReturnSelf();

    AffiliateRankHistoryResource::infolist($schema);

    expect(true)->toBeTrue();
});

it('AffiliateSupportTicketResource configures form schema', function (): void {
    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('schema')->once()->andReturnSelf();

    AffiliateSupportTicketResource::form($schema);

    expect(true)->toBeTrue();
});

it('AffiliateSupportTicketResource configures table schema', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('filters')->once()->andReturnSelf();
    $table->shouldReceive('actions')->once()->andReturnSelf();
    $table->shouldReceive('bulkActions')->once()->andReturnSelf();

    AffiliateSupportTicketResource::table($table);

    expect(true)->toBeTrue();
});

it('AffiliateSupportTicketResource exposes infolist contract', function (): void {
    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('schema')->once()->andReturnSelf();

    AffiliateSupportTicketResource::infolist($schema);

    expect(true)->toBeTrue();
});

it('AffiliateTaxDocumentResource configures table schema', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('filters')->once()->andReturnSelf();
    $table->shouldReceive('actions')->once()->andReturnSelf();

    AffiliateTaxDocumentResource::table($table);

    expect(true)->toBeTrue();
});

it('AffiliateTaxDocumentResource exposes infolist contract', function (): void {
    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('schema')->once()->andReturnSelf();

    AffiliateTaxDocumentResource::infolist($schema);

    expect(true)->toBeTrue();
});

it('AffiliateLinkResource exposes infolist contract', function (): void {
    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('schema')->once()->andReturnSelf();

    AffiliateLinkResource::infolist($schema);

    expect(true)->toBeTrue();
});

it('AffiliatePayoutResource exposes infolist contract', function (): void {
    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('components')->once()->andReturnSelf();

    AffiliatePayoutResource::infolist($schema);

    expect(true)->toBeTrue();
});

it('AffiliateInfolist configures finance sections', function (): void {
    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('components')->once()->andReturnSelf();

    AffiliateInfolist::configure($schema);

    expect(true)->toBeTrue();
});

it('AffiliateFraudSignalResource configures table schema', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('filters')->once()->andReturnSelf();
    $table->shouldReceive('actions')->once()->andReturnSelf();
    $table->shouldReceive('bulkActions')->once()->andReturnSelf();
    $table->shouldReceive('defaultSort')->once()->andReturnSelf();

    AffiliateFraudSignalResource::table($table);

    expect(true)->toBeTrue();
});

it('AffiliateRankResource configures form, table, and infolist contracts', function (): void {
    $form = Mockery::mock(Schema::class);
    $form->shouldReceive('schema')->once()->andReturnSelf();

    $table = Mockery::mock(Table::class);
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('actions')->once()->andReturnSelf();
    $table->shouldReceive('bulkActions')->once()->andReturnSelf();

    $infolist = Mockery::mock(Schema::class);
    $infolist->shouldReceive('schema')->once()->andReturnSelf();

    AffiliateRankResource::form($form);
    AffiliateRankResource::table($table);
    AffiliateRankResource::infolist($infolist);

    expect(true)->toBeTrue();
});

it('AffiliateNetworkResource configures table and infolist contracts', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('defaultSort')->once()->andReturnSelf();

    $infolist = Mockery::mock(Schema::class);
    $infolist->shouldReceive('schema')->once()->andReturnSelf();

    AffiliateNetworkResource::table($table);
    AffiliateNetworkResource::infolist($infolist);

    expect(true)->toBeTrue();
});

it('AffiliateRankHistoryResource configures table and infolist contracts', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('actions')->once()->andReturnSelf();

    $infolist = Mockery::mock(Schema::class);
    $infolist->shouldReceive('schema')->once()->andReturnSelf();

    AffiliateRankHistoryResource::table($table);
    AffiliateRankHistoryResource::infolist($infolist);

    expect(true)->toBeTrue();
});

it('AffiliateProgram relation managers exist', function (): void {
    expect(class_exists(TiersRelationManager::class))->toBeTrue()
        ->and(class_exists(MembershipsRelationManager::class))->toBeTrue()
        ->and(class_exists(CreativesRelationManager::class))->toBeTrue();
});

it('Affiliate finance relation managers exist', function (): void {
    expect(class_exists(PayoutsRelationManager::class))->toBeTrue()
        ->and(class_exists(PayoutMethodsRelationManager::class))->toBeTrue()
        ->and(class_exists(PayoutHoldsRelationManager::class))->toBeTrue()
        ->and(class_exists(PayoutEventsRelationManager::class))->toBeTrue();
});

it('Affiliate commission relation managers exist', function (): void {
    expect(class_exists(CommissionRulesRelationManager::class))->toBeTrue()
        ->and(class_exists(CommissionPromotionsRelationManager::class))->toBeTrue();
});
