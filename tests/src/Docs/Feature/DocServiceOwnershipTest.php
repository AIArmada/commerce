<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\DocPayment;
use AIArmada\Docs\Services\DocService;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', false);
    config()->set('docs.owner.auto_assign_on_create', true);
});

test('doc service assigns explicit owners and blocks cross-owner payments', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Docs Owner A',
        'email' => 'docs-owner-a@example.test',
        'password' => bcrypt('password'),
    ]);
    $ownerB = User::query()->create([
        'name' => 'Docs Owner B',
        'email' => 'docs-owner-b@example.test',
        'password' => bcrypt('password'),
    ]);

    $service = app(DocService::class);
    $doc = $service->createFromType(
        DocType::Invoice,
        [
            'total_minor' => 100,
            'currency' => 'MYR',
        ],
        $ownerA,
    );
    OwnerContext::withOwner($ownerA, static fn (): mixed => $doc->markAsSent());

    expect($doc->owner_type)->toBe($ownerA->getMorphClass())
        ->and((string) $doc->owner_id)->toBe((string) $ownerA->getKey());

    expect(fn (): mixed => OwnerContext::withOwner($ownerB, fn (): DocPayment => $service->recordPayment($doc, [
        'amount_minor' => 25,
        'payment_method' => 'cash',
    ])))->toThrow(AuthorizationException::class);

    OwnerContext::withOwner($ownerA, function () use ($doc): void {
        expect($doc->payments()->count())->toBe(0);
    });
});
