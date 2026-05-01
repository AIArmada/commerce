<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('doc template relationships', function (): void {
    $template = DocTemplate::factory()->create();
    $doc = Doc::factory()->create(['doc_template_id' => $template->id]);

    expect($template->docs)->toHaveCount(1)
        ->and($template->docs->first()->id)->toBe($doc->id);
});

test('doc template set as default logic', function (): void {
    // Create existing default
    $default = DocTemplate::factory()->create([
        'doc_type' => 'invoice',
        'is_default' => true,
    ]);

    // Create new template
    $new = DocTemplate::factory()->create([
        'doc_type' => 'invoice',
        'is_default' => false,
    ]);

    // Set new as default
    $new->setAsDefault();

    expect($new->fresh()->is_default)->toBeTrue();
    expect($default->fresh()->is_default)->toBeFalse();
});

test('doc template set as default respects owner', function (): void {
    config(['docs.owner.enabled' => true]);
    $migration = require __DIR__ . '/../../../../../packages/docs/database/migrations/2000_06_01_000006_ensure_owner_columns_on_docs_related_tables.php';
    $migration->up();

    // Global default
    $global = DocTemplate::factory()->create([
        'doc_type' => 'invoice',
        'is_default' => true,
        'owner_type' => null,
    ]);

    $owner = User::query()->create([
        'name' => 'Template Owner',
        'email' => 'template-owner@example.test',
        'password' => bcrypt('password'),
    ]);

    $ownedDefault = null;
    $ownedNew = null;

    OwnerContext::withOwner($owner, function () use (&$ownedDefault, &$ownedNew): void {
        $ownedDefault = DocTemplate::factory()->create([
            'doc_type' => 'invoice',
            'is_default' => true,
        ]);

        $ownedNew = DocTemplate::factory()->create([
            'doc_type' => 'invoice',
            'is_default' => false,
        ]);

        $ownedNew->setAsDefault();
    });

    expect($ownedDefault)->toBeInstanceOf(DocTemplate::class);
    expect($ownedNew)->toBeInstanceOf(DocTemplate::class);

    expect($ownedNew->fresh()->is_default)->toBeTrue();
    expect($ownedDefault->fresh()->is_default)->toBeFalse();
    // Global should stay default for global context
    expect($global->fresh()->is_default)->toBeTrue();
});

test('doc template deleting logic', function (): void {
    $template = DocTemplate::factory()->create();
    $doc = Doc::factory()->create(['doc_template_id' => $template->id]);

    $template->delete();

    expect($doc->fresh()->doc_template_id)->toBeNull();
});

test('doc template default scope', function (): void {
    $default = DocTemplate::factory()->create([
        'doc_type' => 'invoice',
        'is_default' => true,
    ]);
    DocTemplate::factory()->create([
        'doc_type' => 'invoice',
        'is_default' => false,
    ]);

    $found = DocTemplate::query()->default('invoice')->first();
    expect($found->id)->toBe($default->id);
});
