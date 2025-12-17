<?php

declare(strict_types=1);

use AIArmada\Docs\Models\DocTemplate;
use AIArmada\FilamentDocs\Resources\DocTemplateResource;
use Filament\Support\Icons\Heroicon;

test('doc template resource has correct model and labels', function (): void {
    expect(DocTemplateResource::getModel())->toBe(DocTemplate::class);
    expect(DocTemplateResource::getNavigationIcon())->toBe(Heroicon::OutlinedDocumentDuplicate);
    expect(DocTemplateResource::getRecordTitleAttribute())->toBe('name');
    expect(DocTemplateResource::getNavigationLabel())->toBe('Templates');
    expect(DocTemplateResource::getModelLabel())->toBe('Template');
    expect(DocTemplateResource::getPluralModelLabel())->toBe('Templates');
    expect(DocTemplateResource::getTenantOwnershipRelationshipName())->toBe('owner');
});

test('doc template resource has correct pages', function (): void {
    $pages = DocTemplateResource::getPages();

    expect($pages)->toHaveKey('index');
    expect($pages)->toHaveKey('create');
    expect($pages)->toHaveKey('view');
    expect($pages)->toHaveKey('edit');
});

test('doc template resource has correct relations', function (): void {
    $relations = DocTemplateResource::getRelations();

    expect($relations)->toBeArray();
});

test('doc template resource navigation badge color', function (): void {
    expect(DocTemplateResource::getNavigationBadgeColor())->toBe('gray');
});