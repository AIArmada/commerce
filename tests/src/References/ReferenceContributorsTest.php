<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\References\Actions\SyncReferenceContributors;
use AIArmada\References\Enums\ReferenceContributorRole;
use AIArmada\References\Enums\ReferenceRecordKind;
use AIArmada\References\Enums\ReferenceStatus;
use AIArmada\References\Enums\ReferenceType;
use AIArmada\References\Models\Reference;
use AIArmada\References\Models\ReferenceContributor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config()->set('references.owner.enabled', true);
});

function makeContributorUser(string $email): User
{
    return User::query()->create([
        'name' => 'Contributor',
        'email' => $email,
        'password' => 'secret',
    ]);
}

function makeWork(string $title = 'Contributor Work'): Reference
{
    return Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => $title,
    ]);
}

test('syncs contributors as an unordered UUID set stable by contributor id', function (): void {
    $first = makeContributorUser('contrib-first@example.test');
    $second = makeContributorUser('contrib-second@example.test');
    $work = makeWork();

    (new SyncReferenceContributors)->handle(
        $work,
        ReferenceContributorRole::Author,
        $first->getMorphClass(),
        [(string) $second->getKey(), (string) $first->getKey(), (string) $second->getKey(), ''],
    );

    $stored = $work->contributorsForRole(ReferenceContributorRole::Author)->pluck('contributor_id')->all();
    $expected = [(string) $first->getKey(), (string) $second->getKey()];
    sort($expected);

    expect($stored)->toEqualCanonicalizing($expected)
        ->and(ReferenceContributor::query()->count())->toBe(2);

    (new SyncReferenceContributors)->handle($work, ReferenceContributorRole::Author, $first->getMorphClass(), []);

    expect($work->contributorsForRole(ReferenceContributorRole::Author)->count())->toBe(0);
});

test('rejects non-UUID contributor ids and unknown roles', function (): void {
    $user = makeContributorUser('contrib-invalid@example.test');
    $work = makeWork();

    expect(fn (): Reference => (new SyncReferenceContributors)->handle(
        $work,
        ReferenceContributorRole::Author,
        $user->getMorphClass(),
        ['not-a-uuid'],
    ))->toThrow(InvalidArgumentException::class, 'UUID');

    expect(fn (): Reference => (new SyncReferenceContributors)->handle(
        $work,
        'ghost',
        $user->getMorphClass(),
        [(string) $user->getKey()],
    ))->toThrow(InvalidArgumentException::class, 'role');

    expect(fn (): Reference => (new SyncReferenceContributors)->handle(
        $work,
        ReferenceContributorRole::Author,
        '  ',
        [(string) $user->getKey()],
    ))->toThrow(InvalidArgumentException::class, 'type');

    $unpersisted = new Reference([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => 'Unpersisted',
    ]);

    expect(fn (): Reference => (new SyncReferenceContributors)->handle(
        $unpersisted,
        ReferenceContributorRole::Author,
        $user->getMorphClass(),
        [(string) $user->getKey()],
    ))->toThrow(InvalidArgumentException::class, 'persisted');
});

test('stores authors on works only while other roles may live on children', function (): void {
    $author = makeContributorUser('contrib-author@example.test');
    $editor = makeContributorUser('contrib-editor@example.test');
    $work = makeWork();

    $edition = Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => 'Contributor Edition',
        'record_kind' => ReferenceRecordKind::Edition,
        'parent_id' => $work->getKey(),
        'edition_number' => 1,
    ]);

    expect(fn (): Reference => (new SyncReferenceContributors)->handle(
        $edition,
        ReferenceContributorRole::Author,
        $author->getMorphClass(),
        [(string) $author->getKey()],
    ))->toThrow(InvalidArgumentException::class, 'works');

    (new SyncReferenceContributors)->handle(
        $edition,
        ReferenceContributorRole::Editor,
        $editor->getMorphClass(),
        [(string) $editor->getKey()],
    );

    expect($edition->contributorsForRole(ReferenceContributorRole::Author)->count())->toBe(0)
        ->and($edition->contributorsForRole(ReferenceContributorRole::Editor)->count())->toBe(1);
});

test('inherits effective contributors from the root work', function (): void {
    $author = makeContributorUser('contrib-inherit@example.test');
    $work = makeWork();

    (new SyncReferenceContributors)->handle(
        $work,
        ReferenceContributorRole::Author,
        $author->getMorphClass(),
        [(string) $author->getKey()],
    );

    $edition = Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => 'Inherit Edition',
        'record_kind' => ReferenceRecordKind::Edition,
        'parent_id' => $work->getKey(),
        'edition_number' => 1,
    ]);

    $part = Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => 'Inherit Part',
        'record_kind' => ReferenceRecordKind::Part,
        'parent_id' => $edition->getKey(),
    ]);

    expect($edition->effectiveContributors(ReferenceContributorRole::Author)->pluck('contributor_id')->all())
        ->toBe([(string) $author->getKey()])
        ->and($part->effectiveContributors(ReferenceContributorRole::Author)->pluck('contributor_id')->all())
        ->toBe([(string) $author->getKey()])
        ->and($edition->contributorsForRole(ReferenceContributorRole::Author)->count())->toBe(0);
});

test('deleting a reference removes its contributor links', function (): void {
    $author = makeContributorUser('contrib-cascade@example.test');
    $editor = makeContributorUser('contrib-cascade-editor@example.test');
    $work = makeWork();

    (new SyncReferenceContributors)->handle(
        $work,
        ReferenceContributorRole::Author,
        $author->getMorphClass(),
        [(string) $author->getKey()],
    );

    $edition = Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => 'Cascade Edition',
        'record_kind' => ReferenceRecordKind::Edition,
        'parent_id' => $work->getKey(),
        'edition_number' => 1,
    ]);

    (new SyncReferenceContributors)->handle(
        $edition,
        ReferenceContributorRole::Editor,
        $editor->getMorphClass(),
        [(string) $editor->getKey()],
    );

    expect(ReferenceContributor::query()->count())->toBe(2);

    $work->delete();

    expect(ReferenceContributor::query()->count())->toBe(0);
});

test('blocks contributor sync against another owner even for a retained model', function (): void {
    $owner = makeContributorUser('contrib-owner@example.test');
    $other = makeContributorUser('contrib-other@example.test');
    $work = OwnerContext::withOwner($owner, fn (): Reference => makeWork('Owned Work'));

    expect(fn () => OwnerContext::withOwner($other, fn () => (new SyncReferenceContributors)->handle(
        $work,
        ReferenceContributorRole::Author,
        $other->getMorphClass(),
        [(string) $other->getKey()],
    )))->toThrow(AuthorizationException::class);

    expect(ReferenceContributor::query()->where('reference_id', $work->getKey())->count())->toBe(0);
});

test('repeated sync keeps a single contributor identity', function (): void {
    $author = makeContributorUser('contrib-repeat@example.test');
    $work = makeWork();
    $action = new SyncReferenceContributors;
    $ids = [(string) $author->getKey()];

    $action->handle($work, ReferenceContributorRole::Author, $author->getMorphClass(), $ids);
    $first = $work->contributors()->firstOrFail()->getKey();
    $action->handle($work, ReferenceContributorRole::Author, $author->getMorphClass(), $ids);

    expect($work->contributors()->count())->toBe(1)
        ->and($work->contributors()->firstOrFail()->getKey())->toBe($first);
});

test('contributor queries inherit reference owner visibility', function (): void {
    $owner = makeContributorUser('contributor-read-owner@example.test');
    $other = makeContributorUser('contributor-read-other@example.test');
    $work = OwnerContext::withOwner($owner, function () use ($owner): Reference {
        $work = makeWork('Private authors');
        (new SyncReferenceContributors)->handle($work, ReferenceContributorRole::Author, $owner->getMorphClass(), [(string) $owner->getKey()]);

        return $work;
    });

    expect(OwnerContext::withOwner($owner, fn (): int => ReferenceContributor::query()->count()))->toBe(1)
        ->and(OwnerContext::withOwner($other, fn (): int => ReferenceContributor::query()->count()))->toBe(0)
        ->and(OwnerContext::withOwner($other, fn (): int => $work->contributors()->count()))->toBe(0);
});

test('a work cannot change kind while it owns authors', function (): void {
    $author = makeContributorUser('contributor-kind@example.test');
    $parent = makeWork('New parent work');
    $work = makeWork('Authored work');
    (new SyncReferenceContributors)->handle($work, ReferenceContributorRole::Author, $author->getMorphClass(), [(string) $author->getKey()]);

    expect(fn () => $work->update([
        'record_kind' => ReferenceRecordKind::Edition,
        'parent_id' => $parent->getKey(),
    ]))->toThrow(ValidationException::class, 'Remove authorship');

    expect($work->fresh()->record_kind)->toBe(ReferenceRecordKind::Work)
        ->and($work->contributors()->count())->toBe(1);
});

test('canonicalizes UUID case and rejects non-model contributor types', function (): void {
    $author = makeContributorUser('contributor-canonical@example.test');
    $work = makeWork();
    $id = (string) $author->getKey();
    $action = new SyncReferenceContributors;

    $action->handle($work, ReferenceContributorRole::Author, $author->getMorphClass(), [$id, mb_strtoupper($id)]);

    expect($work->contributors()->count())->toBe(1);

    expect(fn () => $action->handle($work, ReferenceContributorRole::Author, stdClass::class, [$id]))
        ->toThrow(InvalidArgumentException::class, 'type');

    expect($work->contributors()->count())->toBe(1);
});

test('direct contributor writes cannot bypass reference owner authorization', function (): void {
    $owner = makeContributorUser('contributor-write-owner@example.test');
    $other = makeContributorUser('contributor-write-other@example.test');
    $work = OwnerContext::withOwner($owner, fn (): Reference => makeWork('Protected contributor work'));

    expect(fn () => OwnerContext::withOwner($other, fn () => $work->getConnection()->transaction(
        fn () => $work->contributors()->create([
            'role' => ReferenceContributorRole::Author,
            'contributor_type' => $other->getMorphClass(),
            'contributor_id' => $other->getKey(),
        ]),
    )))->toThrow(AuthorizationException::class);

    $contributor = OwnerContext::withOwner($owner, function () use ($owner, $work): ReferenceContributor {
        (new SyncReferenceContributors)->handle($work, ReferenceContributorRole::Author, $owner->getMorphClass(), [(string) $owner->getKey()]);

        return $work->contributors()->firstOrFail();
    });

    expect(fn () => OwnerContext::withOwner($other, fn () => $work->getConnection()->transaction(
        fn () => $contributor->delete(),
    )))->toThrow(AuthorizationException::class);

    expect(OwnerContext::withOwner($owner, fn (): int => $work->contributors()->count()))->toBe(1);
});
