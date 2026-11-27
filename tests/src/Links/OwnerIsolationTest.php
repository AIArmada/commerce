<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Links\Actions\CreateLink;
use AIArmada\Links\Actions\DeactivateLink;
use AIArmada\Links\Actions\DeleteLink;
use AIArmada\Links\Actions\DeleteLinkForSubject;
use AIArmada\Links\Actions\RecordLinkClick;
use AIArmada\Links\Actions\UpdateLink;
use AIArmada\Links\Models\Link;
use AIArmada\Links\Models\LinkClick;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

test('link reads are isolated between owners', function (): void {
    $ownerA = User::create(['name' => 'Owner A', 'email' => 'owner-a-' . uniqid() . '@example.com', 'password' => 'secret']);
    $ownerB = User::create(['name' => 'Owner B', 'email' => 'owner-b-' . uniqid() . '@example.com', 'password' => 'secret']);

    OwnerContext::withOwner($ownerA, fn () => CreateLink::run([
        'name' => 'Owner A link',
        'slug' => 'owner-a-link',
        'destination_url' => 'https://merchant.example/a',
    ]));

    OwnerContext::withOwner($ownerB, function (): void {
        expect(Link::query()->count())->toBe(0);
        expect(LinkClick::query()->count())->toBe(0);
    });

    OwnerContext::withOwner($ownerA, function (): void {
        expect(Link::query()->count())->toBe(1);
    });
});

test('public redirects work without owner context and keep the link owner on clicks', function (): void {
    $owner = User::create(['name' => 'Link Owner', 'email' => 'link-owner-' . uniqid() . '@example.com', 'password' => 'secret']);

    $link = OwnerContext::withOwner($owner, fn () => CreateLink::run([
        'name' => 'Public link',
        'slug' => 'public-link',
        'destination_url' => 'https://merchant.example/public',
    ]));

    OwnerContext::withOwner(null, function (): void {
        $this->get('/go/public-link')->assertRedirect('https://merchant.example/public');
    });

    $click = LinkClick::query()->withoutOwnerScope()->first();

    expect($click)->not->toBeNull();
    expect($click->owner_type)->toBe($link->owner_type);
    expect($click->owner_id)->toBe($link->owner_id);
});

test('link management re-resolves supplied models under the current owner', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $link = OwnerContext::withOwner($ownerA, fn () => CreateLink::run([
        'name' => 'Owner A',
        'slug' => 'owner-a-mutation',
        'destination_url' => 'https://merchant.example/a',
    ]));

    OwnerContext::withOwner($ownerB, function () use ($link): void {
        expect(fn () => UpdateLink::run($link, ['name' => 'Forged']))
            ->toThrow(ModelNotFoundException::class)
            ->and(fn () => DeactivateLink::run($link))->toThrow(ModelNotFoundException::class)
            ->and(fn () => app(DeleteLink::class)->handle($link))->toThrow(ModelNotFoundException::class);
    });

    OwnerContext::withOwner($ownerA, function () use ($link): void {
        expect($link->fresh()->name)->toBe('Owner A')
            ->and($link->fresh()->deactivated_at)->toBeNull();
    });
});

test('public redirects refuse links whose owner no longer exists', function (): void {
    $owner = User::factory()->create();
    $link = OwnerContext::withOwner($owner, fn () => CreateLink::run([
        'name' => 'Orphaned link',
        'slug' => 'orphaned-owner-link',
        'destination_url' => 'https://merchant.example/orphaned',
    ]));
    $owner->delete();

    $this->get('/go/' . $link->slug)->assertGone();

    expect(LinkClick::query()->withoutOwnerScope()->where('link_id', $link->id)->count())->toBe(0);
});

test('subject cascade deletion is owner-safe across tenant, global, and absent contexts', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();
    $subjectId = (string) Str::uuid();

    $link = OwnerContext::withOwner($ownerA, fn () => CreateLink::run([
        'name' => 'Subject cascade',
        'destination_url' => 'https://merchant.example/subject-cascade',
        'subject_type' => 'offer-link',
        'subject_id' => $subjectId,
    ]));

    app(RecordLinkClick::class)->handle($link);
    $delete = app(DeleteLinkForSubject::class);

    OwnerContext::withOwner($ownerB, function () use ($delete, $link, $subjectId): void {
        expect($delete->handle((string) $link->getKey(), 'offer-link', $subjectId))->toBeTrue();
    });

    OwnerContext::withOwner(null, function () use ($delete, $link, $subjectId): void {
        expect($delete->handle((string) $link->getKey(), 'offer-link', $subjectId))->toBeTrue();
    });

    expect(Link::query()->withoutOwnerScope()->whereKey($link->getKey())->exists())->toBeTrue()
        ->and(LinkClick::query()->withoutOwnerScope()->where('link_id', $link->getKey())->exists())->toBeTrue();

    OwnerContext::withOwner($ownerA, function () use ($delete, $link, $subjectId): void {
        expect($delete->handle((string) Str::uuid(), 'offer-link', $subjectId))->toBeTrue()
            ->and($delete->handle((string) $link->getKey(), 'offer-link', (string) Str::uuid()))->toBeFalse()
            ->and($delete->handle((string) $link->getKey(), 'offer-link', $subjectId))->toBeTrue();
    });

    expect(Link::query()->withoutOwnerScope()->whereKey($link->getKey())->exists())->toBeFalse()
        ->and(LinkClick::query()->withoutOwnerScope()->where('link_id', $link->getKey())->exists())->toBeFalse();

    app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

    expect(fn () => $delete->handle((string) $link->getKey(), 'offer-link', $subjectId))
        ->toThrow(NoCurrentOwnerException::class);
});

test('subject cascade deletion never widens to global links when include-global is enabled', function (): void {
    config(['links.owner.include_global' => true]);

    $owner = User::factory()->create();
    $subjectId = (string) Str::uuid();
    $link = OwnerContext::withOwner(null, fn () => CreateLink::run([
        'name' => 'Global subject cascade',
        'destination_url' => 'https://merchant.example/global-subject-cascade',
        'subject_type' => 'offer-link',
        'subject_id' => $subjectId,
    ]));

    $delete = app(DeleteLinkForSubject::class);

    OwnerContext::withOwner($owner, function () use ($delete, $link, $subjectId): void {
        expect($delete->handle((string) $link->getKey(), 'offer-link', $subjectId))->toBeTrue();
    });

    expect(Link::query()->withoutOwnerScope()->whereKey($link->getKey())->exists())->toBeTrue();

    OwnerContext::withOwner(null, function () use ($delete, $link, $subjectId): void {
        expect($delete->handle((string) $link->getKey(), 'offer-link', $subjectId))->toBeTrue();
    });
});
