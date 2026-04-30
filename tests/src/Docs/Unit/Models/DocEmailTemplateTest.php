<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Models\DocEmailTemplate;
use Illuminate\Database\QueryException;

it('allows same slug across different owners and blocks duplicate slug for same owner', function (): void {
    config()->set('docs.owner.enabled', true);

    $ownerA = User::query()->create([
        'name' => 'Template Owner A',
        'email' => 'template-owner-a@example.test',
        'password' => bcrypt('password'),
    ]);

    $ownerB = User::query()->create([
        'name' => 'Template Owner B',
        'email' => 'template-owner-b@example.test',
        'password' => bcrypt('password'),
    ]);

    $createTemplate = static fn (string $name): DocEmailTemplate => DocEmailTemplate::query()->create([
        'name' => $name,
        'slug' => 'shared-slug',
        'doc_type' => 'invoice',
        'trigger' => 'send',
        'subject' => 'Subject',
        'body' => 'Body',
        'is_active' => true,
    ]);

    OwnerContext::withOwner($ownerA, static fn (): DocEmailTemplate => $createTemplate('Owner A Template'));
    OwnerContext::withOwner($ownerB, static fn (): DocEmailTemplate => $createTemplate('Owner B Template'));

    expect(fn (): mixed => OwnerContext::withOwner($ownerA, static fn (): DocEmailTemplate => $createTemplate('Owner A Duplicate')))
        ->toThrow(QueryException::class);
});
