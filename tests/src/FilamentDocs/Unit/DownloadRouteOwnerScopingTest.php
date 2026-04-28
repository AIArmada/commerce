<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Models\Doc;
use AIArmada\FilamentDocs\FilamentDocsServiceProvider;
use Illuminate\Support\Facades\Storage;

uses(TestCase::class);

beforeEach(function (): void {
    app()->register(FilamentDocsServiceProvider::class);

    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', false);

    config()->set('docs.storage.disk', 'local');
    Storage::fake('local');
});

it('allows same-tenant PDF downloads and blocks cross-tenant downloads', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-download@example.test',
        'password' => bcrypt('password'),
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b-download@example.test',
        'password' => bcrypt('password'),
    ]);

    Storage::disk('local')->put('docs/a.pdf', 'pdf-bytes');
    Storage::disk('local')->put('docs/b.pdf', 'pdf-bytes');

    $docA = Doc::factory()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => (string) $ownerA->getKey(),
        'doc_type' => 'invoice',
        'doc_number' => "INV/2025\n0001",
        'pdf_path' => 'docs/a.pdf',
    ]);

    $docB = Doc::factory()->create([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => (string) $ownerB->getKey(),
        'doc_type' => 'invoice',
        'doc_number' => 'INV/2025 0002',
        'pdf_path' => 'docs/b.pdf',
    ]);

    OwnerContext::withOwner($ownerA, function () use ($ownerA, $docA, $docB): void {
        $this->actingAs($ownerA);

        $this->get(route('filament-docs.download', ['doc' => (string) $docA->getKey()]))
            ->assertOk();

        $this->get(route('filament-docs.download', ['doc' => (string) $docB->getKey()]))
            ->assertNotFound();
    });
});
