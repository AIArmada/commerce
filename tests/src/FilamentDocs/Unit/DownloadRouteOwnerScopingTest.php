<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Models\Doc;
use AIArmada\FilamentDocs\FilamentDocsServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

uses(TestCase::class);

beforeEach(function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', false);

    app()->register(FilamentDocsServiceProvider::class);

    config()->set('docs.storage.disk', 'local');
    Storage::fake('local');

    app()->scoped(OwnerResolverInterface::class, static fn (): OwnerResolverInterface => new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            $user = auth()->user();

            return $user instanceof Model ? $user : null;
        }
    });
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

    $docA = OwnerContext::withOwner($ownerA, fn (): Doc => Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => "INV/2025\n0001",
        'pdf_path' => 'docs/a.pdf',
    ]));

    $docB = OwnerContext::withOwner($ownerB, fn (): Doc => Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => 'INV/2025 0002',
        'pdf_path' => 'docs/b.pdf',
    ]));

    $this->actingAs($ownerA);

    $this->get(route('filament-docs.download', ['doc' => (string) $docA->getKey()]))
        ->assertOk();

    $this->get(route('filament-docs.download', ['doc' => (string) $docB->getKey()]))
        ->assertNotFound();
});

it('fails closed when owner context is missing', function (): void {
    $owner = User::query()->create([
        'name' => 'Owner Missing Context',
        'email' => 'owner-missing-context@example.test',
        'password' => bcrypt('password'),
    ]);

    Storage::disk('local')->put('docs/missing-context.pdf', 'pdf-bytes');

    $doc = OwnerContext::withOwner($owner, fn (): Doc => Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => 'INV-NEEDS-OWNER-001',
        'pdf_path' => 'docs/missing-context.pdf',
    ]));

    $this->actingAs($owner);
    $this->withoutExceptionHandling();

    app()->scoped(OwnerResolverInterface::class, static fn (): OwnerResolverInterface => new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    expect(fn (): mixed => $this->get(route('filament-docs.download', ['doc' => (string) $doc->getKey()])))
        ->toThrow(NoCurrentOwnerException::class);
});
