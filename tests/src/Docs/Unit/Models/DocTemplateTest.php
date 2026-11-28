<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Contracts\DocumentTemplates;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Features\Templates\Models\DocTemplate;
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
    app(DocumentTemplates::class)->setDefault((string) $new->getKey());

    expect($new->fresh()->is_default)->toBeTrue();
    expect($default->fresh()->is_default)->toBeFalse();
});

test('doc template set as default respects owner', function (): void {
    config(['docs.owner.enabled' => true]);

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

        app(DocumentTemplates::class)->setDefault((string) $ownedNew->getKey());
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

    app(DocumentTemplates::class)->remove((string) $template->getKey());

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

test('creating or editing a default template clears the previous default', function (): void {
    $old = DocTemplate::factory()->create(['doc_type' => 'invoice', 'is_default' => true]);
    $templates = app(DocumentTemplates::class);
    $id = $templates->create([
        'name' => 'New template',
        'slug' => 'new-template',
        'doc_type' => 'invoice',
        'layout' => $old->layout,
        'is_default' => true,
    ]);

    expect($old->fresh()->is_default)->toBeFalse()
        ->and(DocTemplate::query()->findOrFail($id)->is_default)->toBeTrue();

    $templates->update((string) $old->getKey(), ['is_default' => true]);

    expect($old->fresh()->is_default)->toBeTrue()
        ->and(DocTemplate::query()->findOrFail($id)->is_default)->toBeFalse();
});
