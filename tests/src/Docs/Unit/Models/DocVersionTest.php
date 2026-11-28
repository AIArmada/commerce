<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Contracts\DocumentVersions;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Features\Versioning\Models\DocVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('doc version restore', function (): void {
    $doc = Doc::factory()->create(['notes' => 'Current Notes']);
    $snapshot = $doc->toArray();
    $snapshot['notes'] = 'Old Notes';

    $version = DocVersion::factory()->create([
        'doc_id' => $doc->id,
        'snapshot' => $snapshot,
    ]);

    app(DocumentVersions::class)->restore((string) $version->getKey());

    expect($doc->fresh()->notes)->toBe('Old Notes');
});

test('doc version diff', function (): void {
    $v1 = DocVersion::factory()->create(['snapshot' => ['field' => 'Old Value', 'static' => 'Same']]);
    $v2 = DocVersion::factory()->create(['snapshot' => ['field' => 'New Value', 'static' => 'Same']]);

    // Diff v2 against v1 (comparing new to old)
    $diff = $v2->diff($v1);

    expect($diff)->toHaveKey('field')
        ->and($diff['field']['old'])->toBe('Old Value')
        ->and($diff['field']['new'])->toBe('New Value');

    expect($diff)->not->toHaveKey('static');
});

test('version restoration cannot cross owner boundaries', function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', false);

    $owner = User::factory()->create();
    $other = User::factory()->create();

    $doc = OwnerContext::withOwner($owner, fn (): Doc => Doc::factory()->create(['notes' => 'Original']));
    $version = OwnerContext::withOwner($owner, fn (): string => app(DocumentVersions::class)->capture((string) $doc->getKey()));

    expect(fn () => OwnerContext::withOwner($other, fn () => app(DocumentVersions::class)->restore($version)))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    expect(OwnerContext::withOwner($owner, fn () => $doc->fresh()->notes))->toBe('Original');
});
