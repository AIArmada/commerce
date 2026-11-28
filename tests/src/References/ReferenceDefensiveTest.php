<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\References\Actions\SyncReferenceContributors;
use AIArmada\References\Actions\TransitionReferenceStatus;
use AIArmada\References\Enums\ReferenceContributorRole;
use AIArmada\References\Enums\ReferenceRecordKind;
use AIArmada\References\Enums\ReferenceStatus;
use AIArmada\References\Enums\ReferenceType;
use AIArmada\References\Models\Reference;
use AIArmada\References\Models\ReferenceContributor;
use AIArmada\References\Policies\ReferencePolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function makeDefensiveOwner(string $email): User
{
    return User::query()->create([
        'name' => 'Defensive Owner',
        'email' => $email,
        'password' => 'secret',
    ]);
}

function makeDefensiveReference(string $title, array $attributes = []): Reference
{
    return Reference::create(array_merge([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => $title,
    ], $attributes));
}

beforeEach(function (): void {
    config()->set('references.owner.enabled', true);
    config()->set('references.owner.include_global', false);
});

test('honors per-instance table aliases before configuration fallback', function (): void {
    $reference = new Reference;
    $reference->setTable('alias_references');

    $contributor = new ReferenceContributor;
    $contributor->setTable('alias_reference_contributors');

    config()->set('references.database.tables', []);

    expect($reference->getTable())->toBe('alias_references')
        ->and($contributor->getTable())->toBe('alias_reference_contributors')
        ->and((new Reference)->getTable())->toBe('references')
        ->and((new ReferenceContributor)->getTable())->toBe('reference_contributors');
});

test('rejects malformed persisted owner tuples in policy and lock checks', function (): void {
    $owner = makeDefensiveOwner('defensive-owner@example.test');
    $reference = OwnerContext::withOwner($owner, fn (): Reference => makeDefensiveReference('Malformed Owner'));

    DB::table('references')->where('id', $reference->getKey())->update(['owner_id' => null]);

    $broken = Reference::query()->withoutOwnerScope()->whereKey($reference->getKey())->firstOrFail();
    $policy = new ReferencePolicy;

    expect($policy->view($owner, $broken))->toBeFalse()
        ->and($policy->update($owner, $broken))->toBeFalse()
        ->and($policy->delete($owner, $broken))->toBeFalse();

    expect(fn (): bool => OwnerContext::withOwner(null, fn (): bool => $broken->update(['title' => 'Malformed Write'])))
        ->toThrow(AuthorizationException::class, 'malformed');
});

test('rejects invalid parent UUIDs before querying the database', function (): void {
    DB::enableQueryLog();
    DB::flushQueryLog();

    expect(fn (): Reference => makeDefensiveReference('Invalid Parent', ['parent_id' => 'not-a-uuid']))
        ->toThrow(ValidationException::class, 'UUID');

    $queries = collect(DB::getQueryLog());

    expect($queries->contains(fn (array $query): bool => str_contains((string) $query['query'], 'not-a-uuid')))->toBeFalse();
});

test('quiet saves keep owner assignment, validation, slug and publication integrity', function (): void {
    $owner = makeDefensiveOwner('quiet-owner@example.test');

    $reference = OwnerContext::withOwner($owner, fn (): Reference => Reference::withoutEvents(fn (): Reference => makeDefensiveReference('Quiet Published', [
        'status' => ReferenceStatus::Published,
    ])));

    expect($reference->owner_type)->toBe($owner->getMorphClass())
        ->and($reference->owner_id)->toBe($owner->getKey())
        ->and($reference->slug)->toBe('quiet-published')
        ->and($reference->published_at)->not->toBeNull();

    expect(fn (): Reference => Reference::withoutEvents(fn (): Reference => Reference::create(['title' => 'Missing Type'])))
        ->toThrow(ValidationException::class, 'type');

    expect(fn (): Reference => Reference::withoutEvents(fn (): Reference => makeDefensiveReference('Invalid Quiet Year', ['year' => 'soon'])))
        ->toThrow(ValidationException::class, 'Invalid year');
});

test('saveOrIgnore keeps validation instead of opening an unguarded write path', function (): void {
    $reference = new Reference(['title' => 'Missing Type']);

    expect(fn (): bool => $reference->saveOrIgnore())->toThrow(ValidationException::class, 'type');

    expect(Reference::query()->withoutOwnerScope()->where('title', 'Missing Type')->exists())->toBeFalse();
});

test('status transitions keep one lock path and do not refresh or query the parent', function (): void {
    $parent = makeDefensiveReference('Transition Parent');
    $child = makeDefensiveReference('Transition Child', [
        'record_kind' => ReferenceRecordKind::Edition,
        'parent_id' => $parent->getKey(),
    ]);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $returned = (new TransitionReferenceStatus)->handle($child, ReferenceStatus::Published);

    expect($returned)->toBe($child)
        ->and($returned->status)->toBe(ReferenceStatus::Published)
        ->and($returned->published_at)->not->toBeNull();

    $queries = collect(DB::getQueryLog());

    expect($queries->contains(fn (array $query): bool => in_array($parent->getKey(), $query['bindings'], true)))->toBeFalse()
        ->and($queries->filter(fn (array $query): bool => str_starts_with((string) $query['query'], 'select'))->count())->toBe(1)
        ->and($queries->last()['query'])->toStartWith('update');
});

test('unchanged hierarchy saves do not query the parent', function (): void {
    $parent = makeDefensiveReference('Unchanged Parent');
    $child = makeDefensiveReference('Unchanged Child', [
        'record_kind' => ReferenceRecordKind::Edition,
        'parent_id' => $parent->getKey(),
    ]);

    DB::enableQueryLog();
    DB::flushQueryLog();

    expect($child->update(['title' => 'Unchanged Child Updated']))->toBeTrue();

    $queries = collect(DB::getQueryLog());

    expect($queries->contains(fn (array $query): bool => in_array($parent->getKey(), $query['bindings'], true)))->toBeFalse();
});

test('effective contributors resolve retained records by their persisted owner', function (): void {
    $ownerA = makeDefensiveOwner('retained-owner-a@example.test');
    $ownerB = makeDefensiveOwner('retained-owner-b@example.test');
    $author = makeDefensiveOwner('retained-author@example.test');

    $work = OwnerContext::withOwner($ownerA, fn (): Reference => makeDefensiveReference('Retained Work'));
    $edition = OwnerContext::withOwner($ownerA, fn (): Reference => makeDefensiveReference('Retained Edition', [
        'record_kind' => ReferenceRecordKind::Edition,
        'parent_id' => $work->getKey(),
    ]));

    OwnerContext::withOwner($ownerA, fn (): Reference => (new SyncReferenceContributors)->handle(
        $work,
        ReferenceContributorRole::Author,
        $author->getMorphClass(),
        [(string) $author->getKey()],
    ));

    expect(OwnerContext::withOwner($ownerB, fn (): array => $edition->effectiveContributors(ReferenceContributorRole::Author)->pluck('contributor_id')->all()))
        ->toBe([(string) $author->getKey()]);
});

test('effective contributors respect suppressed global parent inheritance', function (): void {
    config()->set('references.owner.include_global', true);

    $owner = makeDefensiveOwner('global-parent-owner@example.test');
    $author = makeDefensiveOwner('global-parent-author@example.test');

    $work = OwnerContext::withOwner(null, fn (): Reference => makeDefensiveReference('Global Parent Work'));
    $edition = OwnerContext::withOwner($owner, fn (): Reference => makeDefensiveReference('Owned Global Parent Edition', [
        'record_kind' => ReferenceRecordKind::Edition,
        'parent_id' => $work->getKey(),
    ]));

    OwnerContext::withOwner(null, fn (): Reference => (new SyncReferenceContributors)->handle(
        $work,
        ReferenceContributorRole::Author,
        $author->getMorphClass(),
        [(string) $author->getKey()],
    ));

    $followed = OwnerContext::withOwner($owner, fn (): Reference => $edition->effectiveContributorReference());
    $suppressed = OwnerContext::withOwner($owner, fn (): Reference => OwnerScopeOverride::withoutIncludeGlobal(
        fn (): Reference => $edition->effectiveContributorReference(),
    ));

    expect($followed->getKey())->toBe($work->getKey())
        ->and($suppressed->getKey())->toBe($edition->getKey())
        ->and(OwnerContext::withOwner($owner, fn (): array => $edition->effectiveContributors(ReferenceContributorRole::Author)->pluck('contributor_id')->all()))
        ->toBe([(string) $author->getKey()]);
});
