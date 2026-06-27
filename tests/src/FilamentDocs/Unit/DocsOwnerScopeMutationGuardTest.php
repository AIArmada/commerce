<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Models\DocTemplate;
use AIArmada\Docs\Support\TemplateBlockRegistry;
use AIArmada\FilamentDocs\Resources\DocEmailTemplateResource;
use AIArmada\FilamentDocs\Resources\DocSequenceResource;
use AIArmada\FilamentDocs\Resources\DocTemplateResource;
use AIArmada\FilamentDocs\Support\DocsOwnerScope;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(TestCase::class);

it('allows read access to global rows but denies mutation in tenant context when include_global is enabled', function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', true);

    $owner = User::query()->create([
        'name' => 'Owner One',
        'email' => 'docs-owner-one@example.test',
        'password' => bcrypt('password'),
    ]);

    $globalTemplate = OwnerContext::withOwner(null, fn (): DocTemplate => DocTemplate::query()->create([
        'name' => 'Global Template',
        'slug' => 'global-template',
        'description' => 'Global',
        'doc_type' => 'invoice',
        'is_default' => false,
        'layout' => TemplateBlockRegistry::defaultLayout(),
        'settings' => [],
    ]));

    OwnerContext::withOwner($owner, function () use ($globalTemplate): void {
        DocsOwnerScope::assertCanAccessRecord($globalTemplate, 'Template not found.');

        expect(fn (): mixed => DocsOwnerScope::assertCanMutateRecord($globalTemplate, 'Template not found.'))
            ->toThrow(NotFoundHttpException::class);
    });
});

it('allows mutation for rows owned by current tenant', function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', true);

    $owner = User::query()->create([
        'name' => 'Owner Two',
        'email' => 'docs-owner-two@example.test',
        'password' => bcrypt('password'),
    ]);

    $ownedTemplate = OwnerContext::withOwner($owner, fn (): DocTemplate => DocTemplate::query()->create([
        'name' => 'Owned Template',
        'slug' => 'owned-template',
        'description' => 'Owned',
        'doc_type' => 'invoice',
        'is_default' => false,
        'layout' => TemplateBlockRegistry::defaultLayout(),
        'settings' => [],
    ]));

    OwnerContext::withOwner($owner, function () use ($ownedTemplate): void {
        DocsOwnerScope::assertCanMutateRecord($ownedTemplate, 'Template not found.');

        expect(true)->toBeTrue();
    });
});

it('applies explicit owner filters to docs template resources', function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', false);

    $owner = User::query()->create([
        'name' => 'Resource Owner',
        'email' => 'docs-resource-owner@example.test',
        'password' => bcrypt('password'),
    ]);

    $resources = [
        DocTemplateResource::class,
        DocSequenceResource::class,
        DocEmailTemplateResource::class,
    ];

    OwnerContext::withOwner($owner, function () use ($resources): void {
        foreach ($resources as $resource) {
            $sql = $resource::getEloquentQuery()->toSql();

            expect($sql)
                ->toContain('owner_type')
                ->toContain('owner_id');
        }
    });
});
