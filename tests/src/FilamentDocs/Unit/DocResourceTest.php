<?php

declare(strict_types=1);

use AIArmada\Docs\Models\Doc;
use AIArmada\FilamentDocs\Resources\DocResource;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\ApprovalsRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\EmailsRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\PaymentsRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\StatusHistoriesRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\VersionsRelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

test('doc resource has correct model and labels', function (): void {
    expect(DocResource::getModel())->toBe(Doc::class);
    expect(DocResource::getNavigationIcon())->toBe(Heroicon::OutlinedDocumentText);
    expect(DocResource::getRecordTitleAttribute())->toBe('doc_number');
    expect(DocResource::getNavigationLabel())->toBe('Documents');
    expect(DocResource::getModelLabel())->toBe('Document');
    expect(DocResource::getPluralModelLabel())->toBe('Documents');
    expect(DocResource::getTenantOwnershipRelationshipName())->toBe('owner');
});

test('doc resource has correct pages', function (): void {
    $pages = DocResource::getPages();

    expect($pages)->toHaveKey('index');
    expect($pages)->toHaveKey('create');
    expect($pages)->toHaveKey('view');
    expect($pages)->toHaveKey('edit');
});

test('doc resource has correct relations', function (): void {
    $relations = DocResource::getRelations();

    expect($relations)->toContain(StatusHistoriesRelationManager::class);
    expect($relations)->toContain(PaymentsRelationManager::class);
    expect($relations)->toContain(EmailsRelationManager::class);
    expect($relations)->toContain(VersionsRelationManager::class);
    expect($relations)->toContain(ApprovalsRelationManager::class);
});



test('doc resource navigation badge color', function (): void {
    expect(DocResource::getNavigationBadgeColor())->toBe('primary');
});