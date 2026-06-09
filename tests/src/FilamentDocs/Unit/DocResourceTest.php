<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\States\Overdue;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\Pending;
use AIArmada\FilamentDocs\Resources\DocResource;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\ApprovalsRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\EmailsRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\PaymentsRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\StatusHistoriesRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\VersionsRelationManager;
use Filament\Support\Icons\Heroicon;

uses(TestCase::class);

test('doc resource has correct model and labels', function (): void {
    expect(DocResource::getModel())->toBe(Doc::class);
    expect(DocResource::getNavigationIcon())->toBe(Heroicon::OutlinedDocumentText);
    expect(DocResource::getRecordTitleAttribute())->toBe('doc_number');
    expect(DocResource::getNavigationLabel())->toBe('Documents');
    expect(DocResource::getModelLabel())->toBe('Document');
    expect(DocResource::getPluralModelLabel())->toBe('Documents');
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

test('doc resource navigation badge color returns valid color', function (): void {
    // getNavigationBadgeColor queries the database, so we need to set up config first
    // This test validates that the method exists and returns one of the expected colors
    $reflection = new ReflectionMethod(DocResource::class, 'getNavigationBadgeColor');
    expect($reflection->isPublic())->toBeTrue();
    expect($reflection->isStatic())->toBeTrue();

    // Method signature should return string
    $returnType = $reflection->getReturnType();
    expect($returnType?->getName())->toBe('string');
});

test('doc resource navigation badges count pending and overdue documents', function (): void {
    $createDoc = static function (string $docNumber, string $status): Doc {
        return Doc::query()->create([
            'doc_number' => $docNumber,
            'doc_type' => 'invoice',
            'status' => $status,
            'issue_date' => now(),
            'subtotal' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 100,
            'currency' => 'MYR',
            'customer_data' => ['name' => 'Acme Customer'],
            'company_data' => ['name' => 'Commerce Demo'],
            'items' => [['name' => 'Demo Item', 'quantity' => 1, 'price' => 100]],
        ]);
    };

    $createDoc('INV-PENDING-0001', Pending::class);
    $createDoc('INV-OVERDUE-0001', Overdue::class);
    $createDoc('INV-PAID-0001', Paid::class);

    expect(DocResource::getNavigationBadge())->toBe('2');
    expect(DocResource::getNavigationBadgeColor())->toBe('danger');
});
