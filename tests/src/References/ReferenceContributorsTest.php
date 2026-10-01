<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\References\Enums\ReferenceContributorRole;
use AIArmada\References\Enums\ReferenceRecordKind;
use AIArmada\References\Enums\ReferenceStatus;
use AIArmada\References\Enums\ReferenceType;
use AIArmada\References\Models\Reference;
use AIArmada\References\Models\ReferenceContributor;

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

    $work->syncContributors(
        ReferenceContributorRole::Author,
        $first->getMorphClass(),
        [(string) $second->getKey(), (string) $first->getKey(), (string) $second->getKey(), ''],
    );

    $stored = $work->contributorsForRole(ReferenceContributorRole::Author)->pluck('contributor_id')->all();
    $expected = [(string) $first->getKey(), (string) $second->getKey()];
    sort($expected);

    expect($stored)->toEqualCanonicalizing($expected)
        ->and(ReferenceContributor::query()->count())->toBe(2);

    $work->syncContributors(ReferenceContributorRole::Author, $first->getMorphClass(), []);

    expect($work->contributorsForRole(ReferenceContributorRole::Author)->count())->toBe(0);
});

test('rejects non-UUID contributor ids and unknown roles', function (): void {
    $user = makeContributorUser('contrib-invalid@example.test');
    $work = makeWork();

    expect(fn (): Reference => $work->syncContributors(
        ReferenceContributorRole::Author,
        $user->getMorphClass(),
        ['not-a-uuid'],
    ))->toThrow(InvalidArgumentException::class, 'UUID');

    expect(fn (): Reference => $work->syncContributors(
        'ghost',
        $user->getMorphClass(),
        [(string) $user->getKey()],
    ))->toThrow(InvalidArgumentException::class, 'role');

    expect(fn (): Reference => $work->syncContributors(
        ReferenceContributorRole::Author,
        '  ',
        [(string) $user->getKey()],
    ))->toThrow(InvalidArgumentException::class, 'type');

    $unpersisted = new Reference([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => 'Unpersisted',
    ]);

    expect(fn (): Reference => $unpersisted->syncContributors(
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

    expect(fn (): Reference => $edition->syncContributors(
        ReferenceContributorRole::Author,
        $author->getMorphClass(),
        [(string) $author->getKey()],
    ))->toThrow(InvalidArgumentException::class, 'works');

    $edition->syncContributors(
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

    $work->syncContributors(
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

    $work->syncContributors(
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

    $edition->syncContributors(
        ReferenceContributorRole::Editor,
        $editor->getMorphClass(),
        [(string) $editor->getKey()],
    );

    expect(ReferenceContributor::query()->count())->toBe(2);

    $work->delete();

    expect(ReferenceContributor::query()->count())->toBe(0);
});
