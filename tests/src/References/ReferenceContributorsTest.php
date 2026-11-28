<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\References\Actions\SyncReferenceContributors;
use AIArmada\References\Actions\ValidateContributorIdentity;
use AIArmada\References\Enums\ReferenceContributorRole;
use AIArmada\References\Enums\ReferenceRecordKind;
use AIArmada\References\Enums\ReferenceStatus;
use AIArmada\References\Enums\ReferenceType;
use AIArmada\References\Models\Reference;
use AIArmada\References\Models\ReferenceContributor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ReferenceConstructorTrap extends Model
{
    public function __construct(array $attributes = [])
    {
        throw new RuntimeException('Contributor type canonicalization executed the model constructor.');
    }
}

final class SecondaryConnectionContributor extends Model
{
    protected $connection = 'references_secondary';

    protected $table = 'secondary_contributors';

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';
}

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

test('direct contributor writes require an explicit role even when events are suppressed', function (): void {
    $author = makeContributorUser('contrib-role-required@example.test');
    $work = makeWork();
    $edition = Reference::create([
        'type' => ReferenceType::Book,
        'status' => ReferenceStatus::Draft,
        'title' => 'Role Required Edition',
        'record_kind' => ReferenceRecordKind::Edition,
        'parent_id' => $work->getKey(),
        'edition_number' => 1,
    ]);

    $missingRole = new ReferenceContributor([
        'contributor_type' => $author->getMorphClass(),
        'contributor_id' => $author->getKey(),
    ]);
    $missingRole->reference_id = $edition->getKey();

    expect(fn (): bool => $missingRole->save())
        ->toThrow(InvalidArgumentException::class, 'role');

    $quietAuthor = new ReferenceContributor([
        'contributor_type' => $author->getMorphClass(),
        'contributor_id' => $author->getKey(),
        'role' => ReferenceContributorRole::Author,
    ]);
    $quietAuthor->reference_id = $edition->getKey();

    expect(fn (): bool => $quietAuthor->saveQuietly())
        ->toThrow(InvalidArgumentException::class, 'works');

    expect(ReferenceContributor::query()->count())->toBe(0);
});

test('owner-scoped contributor targets must be visible in the current scope', function (): void {
    $ownerA = makeContributorUser('contributor-scope-a@example.test');
    $ownerB = makeContributorUser('contributor-scope-b@example.test');

    $work = OwnerContext::withOwner($ownerA, fn (): Reference => makeWork('Scoped Contributor Work'));
    $foreignTarget = OwnerContext::withOwner($ownerB, fn (): Reference => makeWork('Foreign Contributor Target'));
    $localTarget = OwnerContext::withOwner($ownerA, fn (): Reference => makeWork('Local Contributor Target'));

    $action = new SyncReferenceContributors;

    expect(fn (): Reference => OwnerContext::withOwner($ownerA, fn (): Reference => $action->handle(
        $work,
        ReferenceContributorRole::Editor,
        Reference::class,
        [(string) $foreignTarget->getKey()],
    )))->toThrow(InvalidArgumentException::class, 'outside the current owner scope');

    OwnerContext::withOwner($ownerA, fn (): Reference => $action->handle(
        $work,
        ReferenceContributorRole::Editor,
        Reference::class,
        [(string) $localTarget->getKey()],
    ));

    expect(OwnerContext::withOwner($ownerA, fn (): array => $work->contributorsForRole(ReferenceContributorRole::Editor)->pluck('contributor_id')->all()))
        ->toBe([(string) $localTarget->getKey()]);
});

test('sync keeps query counts bounded and independent of contributor count', function (): void {
    $users = [];

    foreach (range(1, 5) as $index) {
        $users[] = makeContributorUser('contributor-bounded-' . $index . '@example.test');
    }

    $work = makeWork();

    DB::enableQueryLog();
    DB::flushQueryLog();

    (new SyncReferenceContributors)->handle(
        $work,
        ReferenceContributorRole::Editor,
        User::class,
        array_map(static fn (User $user): string => (string) $user->getKey(), $users),
    );

    $queries = collect(DB::getQueryLog());

    expect($queries->count())->toBeLessThanOrEqual(6)
        ->and($work->contributorsForRole(ReferenceContributorRole::Editor)->count())->toBe(5);
});

test('canonicalizes contributor types without executing model constructors', function (): void {
    expect((new ValidateContributorIdentity)->type(ReferenceConstructorTrap::class))
        ->toBe(ReferenceConstructorTrap::class);
});

test('rejects malformed contributor reference ids before querying references', function (): void {
    $author = makeContributorUser('contributor-reference-id@example.test');
    $contributor = new ReferenceContributor([
        'reference_id' => 'not-a-uuid',
        'contributor_type' => $author->getMorphClass(),
        'contributor_id' => $author->getKey(),
        'role' => ReferenceContributorRole::Author,
    ]);

    DB::enableQueryLog();
    DB::flushQueryLog();

    expect(fn (): bool => $contributor->save())
        ->toThrow(InvalidArgumentException::class, 'reference_id');

    expect(collect(DB::getQueryLog())->contains(
        fn (array $query): bool => in_array('not-a-uuid', $query['bindings'], true),
    ))->toBeFalse();
});

test('resolves contributor target connections like morph relations', function (): void {
    config()->set('database.connections.references_secondary', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    DB::purge('references_secondary');

    Schema::connection('references_secondary')->create('secondary_contributors', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    $owner = makeContributorUser('contributor-connection-owner@example.test');
    $inherited = makeContributorUser('contributor-connection-inherited@example.test');

    $explicit = new SecondaryConnectionContributor;
    $explicit->forceFill([
        'id' => (string) Str::uuid(),
        'name' => 'Explicit Secondary Contributor',
    ])->save();

    $work = OwnerContext::withOwner($owner, fn (): Reference => makeWork('Connection Contributor Work'));
    $work->setConnection('testing');

    $originalDefault = (string) config('database.default');
    $action = new SyncReferenceContributors;

    config()->set('database.default', 'references_secondary');

    try {
        OwnerContext::withOwner($owner, function () use ($action, $work, $inherited, $explicit): void {
            $action->handle(
                $work,
                ReferenceContributorRole::Editor,
                $inherited->getMorphClass(),
                [(string) $inherited->getKey()],
            );

            $action->handle(
                $work,
                ReferenceContributorRole::Translator,
                SecondaryConnectionContributor::class,
                [(string) $explicit->getKey()],
            );
        });
    } finally {
        config()->set('database.default', $originalDefault);
    }

    expect(OwnerContext::withOwner($owner, fn (): array => $work->contributorsForRole(ReferenceContributorRole::Editor)->pluck('contributor_id')->all()))
        ->toBe([(string) $inherited->getKey()])
        ->and(OwnerContext::withOwner($owner, fn (): array => $work->contributorsForRole(ReferenceContributorRole::Translator)->pluck('contributor_id')->all()))
        ->toBe([(string) $explicit->getKey()]);
});

test('chunks large contributor validation, inserts, and deletes', function (): void {
    $ids = [];
    $rows = [];

    foreach (range(1, 501) as $index) {
        $id = (string) Str::uuid();
        $ids[] = $id;
        $rows[] = [
            'id' => $id,
            'name' => 'Bulk Contributor ' . $index,
            'email' => 'bulk-contributor-' . $index . '@example.test',
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    foreach (array_chunk($rows, 100) as $chunk) {
        User::query()->insert($chunk);
    }

    $work = makeWork('Bulk Contributor Work');
    $action = new SyncReferenceContributors;

    DB::enableQueryLog();
    DB::flushQueryLog();

    $action->handle($work, ReferenceContributorRole::Editor, User::class, $ids);

    $queries = collect(DB::getQueryLog());
    $inserts = $queries->filter(fn (array $query): bool => str_starts_with((string) $query['query'], 'insert into "reference_contributors"'));
    $validations = $queries->filter(fn (array $query): bool => str_starts_with((string) $query['query'], 'select') && str_contains((string) $query['query'], 'from "users"'));

    expect($inserts)->toHaveCount(2)
        ->and($validations)->toHaveCount(2)
        ->and($work->contributorsForRole(ReferenceContributorRole::Editor)->count())->toBe(501);

    DB::flushQueryLog();

    $action->handle($work, ReferenceContributorRole::Editor, User::class, []);

    $deletes = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_starts_with((string) $query['query'], 'delete from "reference_contributors"'));

    expect($deletes)->toHaveCount(2)
        ->and($work->contributorsForRole(ReferenceContributorRole::Editor)->count())->toBe(0);
});
