<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\References\Enums\ReferencePartType;
use AIArmada\References\Enums\ReferenceRecordKind;
use AIArmada\References\Enums\ReferenceStatus;
use AIArmada\References\Enums\ReferenceType;
use AIArmada\References\Models\Reference;
use AIArmada\References\Policies\ReferencePolicy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config()->set('references.owner.enabled', true);
    config()->set('references.owner.include_global', false);
});

function makeReferenceOwner(string $email): User
{
    return User::query()->create([
        'name' => 'Reference Owner',
        'email' => $email,
        'password' => 'secret',
    ]);
}

test('slugs are unique per owner rather than globally', function (): void {
    $ownerA = makeReferenceOwner('ref-slug-a@example.test');
    $ownerB = makeReferenceOwner('ref-slug-b@example.test');

    OwnerContext::withOwner($ownerA, fn (): Reference => Reference::create([
        'type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'Shared Slug', 'slug' => 'shared-slug',
    ]));

    // Same slug under a different owner stores fine (events bypassed so the
    // global slug suffixer does not rewrite the probe).
    $referenceB = Reference::withoutEvents(fn (): Reference => Reference::query()->create([
        'type' => ReferenceType::Book->value,
        'status' => ReferenceStatus::Draft->value,
        'title' => 'Shared Slug',
        'slug' => 'shared-slug',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]));

    expect($referenceB->slug)->toBe('shared-slug');

    // Same slug under the same owner violates the partial unique index.
    expect(fn () => Reference::withoutEvents(fn (): Reference => Reference::query()->create([
        'type' => ReferenceType::Book->value,
        'status' => ReferenceStatus::Draft->value,
        'title' => 'Shared Slug Again',
        'slug' => 'shared-slug',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ])))->toThrow(QueryException::class);
});

test('rejects invalid reference parents', function (): void {
    $ownerA = makeReferenceOwner('ref-parent-a@example.test');
    $ownerB = makeReferenceOwner('ref-parent-b@example.test');

    $root = OwnerContext::withOwner($ownerA, fn (): Reference => Reference::create([
        'type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'Parent Root',
    ]));
    $child = OwnerContext::withOwner($ownerA, fn (): Reference => Reference::create([
        'type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'Parent Child', 'record_kind' => ReferenceRecordKind::Edition, 'parent_id' => $root->getKey(),
    ]));
    $otherParent = OwnerContext::withOwner($ownerB, fn (): Reference => Reference::create([
        'type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'Other Parent',
    ]));

    expect(fn () => OwnerContext::withOwner($ownerA, fn (): Reference => Reference::create([
        'type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'Missing', 'parent_id' => (string) Str::uuid(),
    ])))->toThrow(ValidationException::class, 'parent_id');

    expect(fn () => OwnerContext::withOwner($ownerA, function () use ($root): void {
        $root->update(['parent_id' => $root->getKey()]);
    }))->toThrow(ValidationException::class, 'own parent');

    expect(fn () => OwnerContext::withOwner($ownerA, function () use ($root, $child): void {
        $root->update(['parent_id' => $child->getKey()]);
    }))->toThrow(ValidationException::class, 'own descendant');

    expect(fn () => OwnerContext::withOwner($ownerA, fn (): Reference => Reference::create([
        'type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'Cross', 'parent_id' => $otherParent->getKey(),
    ])))->toThrow(Exception::class);
});

test('deleting a reference fires events for every descendant', function (): void {
    $root = Reference::create(['type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'Event Root']);
    $child = Reference::create(['type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'Event Child', 'record_kind' => ReferenceRecordKind::Edition, 'parent_id' => $root->getKey()]);
    Reference::create(['type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'Event Grandchild', 'record_kind' => ReferenceRecordKind::Part, 'parent_id' => $child->getKey()]);

    Event::fake();

    $root->delete();

    Event::assertDispatchedTimes('eloquent.deleting: ' . Reference::class, 3);
    Event::assertDispatchedTimes('eloquent.deleted: ' . Reference::class, 3);
});

test('deleting a global parent removes owned descendants too', function (): void {
    config()->set('references.owner.include_global', true);

    $owner = makeReferenceOwner('ref-cascade@example.test');

    $parent = OwnerContext::withOwner(null, fn (): Reference => Reference::create([
        'type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'Global Cascade',
    ]));
    $child = OwnerContext::withOwner($owner, fn (): Reference => Reference::create([
        'type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'Owned Cascade Child', 'record_kind' => ReferenceRecordKind::Part, 'parent_id' => $parent->getKey(),
    ]));

    OwnerContext::withOwner(null, fn (): ?bool => $parent->delete());

    expect(Reference::query()->withoutOwnerScope()->whereKey($child->getKey())->exists())->toBeFalse();
});

test('reference policy is registered and owner-aware', function (): void {
    $ownerA = makeReferenceOwner('ref-policy-a@example.test');
    $ownerB = makeReferenceOwner('ref-policy-b@example.test');

    $referenceA = OwnerContext::withOwner($ownerA, fn (): Reference => Reference::create([
        'type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'Policy A',
    ]));

    expect(Gate::getPolicyFor(Reference::class))->toBeInstanceOf(ReferencePolicy::class);

    $policy = app(ReferencePolicy::class);

    expect(OwnerContext::withOwner($ownerA, fn (): bool => $policy->view($ownerA, $referenceA)))->toBeTrue()
        ->and(OwnerContext::withOwner($ownerB, fn (): bool => $policy->view($ownerB, $referenceA)))->toBeFalse()
        ->and(OwnerContext::withOwner($ownerB, fn (): bool => $policy->delete($ownerB, $referenceA)))->toBeFalse()
        ->and($policy->viewAny($ownerA))->toBeTrue()
        ->and($policy->create($ownerA))->toBeTrue();
});

test('rejects invalid reference fields', function (array $attributes, string $message): void {
    expect(fn () => Reference::create(array_merge([
        'type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'Invalid Fields',
    ], $attributes)))->toThrow(ValidationException::class, $message);
})->with([
    'non-numeric year' => [['year' => 'soon'], 'Invalid year'],
    'absurd year' => [['year' => 99999], 'Invalid year'],
    'long isbn' => [['isbn' => str_repeat('1', 21)], 'Invalid isbn'],
    'non-url' => [['url' => 'not-a-url'], 'Invalid url'],
    'script url' => [['url' => 'javascript:alert(1)'], 'Invalid url'],
    'long language' => [['language' => str_repeat('a', 11)], 'Invalid language'],
    'oversized metadata' => [['metadata' => ['blob' => str_repeat('x', 70000)]], 'Invalid metadata'],
    'non-object parts' => [['reference_parts' => 'parts'], 'Invalid reference_parts'],
]);

test('accepts sane reference fields including ancient years', function (): void {
    $reference = Reference::create([
        'type' => ReferenceType::Article,
        'status' => ReferenceStatus::Draft,
        'title' => 'Sane Fields',
        'year' => -500,
        'isbn' => '978-3-16-148410-0',
        'url' => 'https://example.test/source',
        'language' => 'ms',
        'metadata' => ['source' => 'library'],
    ]);

    expect($reference->year)->toBe(-500);
});

test('resolves the configured table with a matching fallback', function (): void {
    expect((new Reference)->getTable())->toBe('references');

    config()->set('references.database.tables', []);

    expect((new Reference)->getTable())->toBe('references');
});

test('manages structured parts through the shared trait', function (): void {
    $reference = Reference::create([
        'type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'Parts Host',
    ]);

    $reference->setPart(ReferencePartType::Jilid, '3');

    expect($reference->getPart(ReferencePartType::Jilid))->toMatchArray(['type' => 'jilid', 'value' => '3'])
        ->and($reference->hasPart(ReferencePartType::Jilid))->toBeTrue();

    $reference->save();
    $reference->refresh();

    expect($reference->getPart('jilid')['value'])->toBe('3');

    $reference->removePart(ReferencePartType::Jilid);

    expect($reference->hasPart(ReferencePartType::Jilid))->toBeFalse();
});

test('status transitions keep the publish timestamp in sync', function (): void {
    $reference = Reference::create([
        'type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'Lifecycle',
    ]);

    $reference->transitionStatus(ReferenceStatus::Published);

    expect($reference->fresh()->published_at)->not->toBeNull();

    $reference->transitionStatus(ReferenceStatus::Draft);

    expect($reference->fresh()->published_at)->toBeNull()
        ->and($reference->fresh()->status)->toBe(ReferenceStatus::Draft);
});

test('publishing directly still stamps the publish timestamp', function (): void {
    $reference = Reference::create([
        'type' => ReferenceType::Book, 'status' => ReferenceStatus::Published, 'title' => 'Direct Publish',
    ]);

    expect($reference->published_at)->not->toBeNull();
});

test('stores typed editions and edition-specific parts', function (): void {
    $work = Reference::create(['type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'A Work']);
    $edition = Reference::create([
        'type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'An Edition',
        'record_kind' => ReferenceRecordKind::Edition, 'parent_id' => $work->getKey(),
        'edition_number' => 3, 'edition_label' => 'Revised',
    ]);
    $part = Reference::create([
        'type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'A Part',
        'record_kind' => ReferenceRecordKind::Part, 'parent_id' => $edition->getKey(),
    ]);

    expect($work->record_kind)->toBe(ReferenceRecordKind::Work);
    expect($edition->fresh()->edition_number)->toBe(3);
    expect($part->fresh()->parent_id)->toBe($edition->getKey());
});

test('rejects parent kinds and kind changes which invalidate children', function (): void {
    $work = Reference::create(['type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'Work']);
    $part = Reference::create([
        'type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'Part',
        'record_kind' => ReferenceRecordKind::Part, 'parent_id' => $work->getKey(),
    ]);

    expect(fn () => Reference::create([
        'type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'Invalid Edition',
        'record_kind' => ReferenceRecordKind::Edition, 'parent_id' => $part->getKey(),
    ]))->toThrow(ValidationException::class);
    expect(fn () => $work->update(['record_kind' => ReferenceRecordKind::Part, 'parent_id' => $part->getKey()]))
        ->toThrow(ValidationException::class);
});

test('rejects edition fields and missing parents with field errors', function (array $attributes, string $field): void {
    try {
        Reference::create(array_merge([
            'type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'Invalid Shape',
        ], $attributes));
        test()->fail('Expected validation to reject the reference.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }
})->with([
    'edition without parent' => [['record_kind' => ReferenceRecordKind::Edition], 'parent_id'],
    'part without parent' => [['record_kind' => ReferenceRecordKind::Part], 'parent_id'],
    'work with edition data' => [['edition_number' => 2], 'record_kind'],
    'negative edition number' => [['edition_number' => -1], 'edition_number'],
    'decimal edition number' => [['edition_number' => '1.5'], 'edition_number'],
    'oversized edition label' => [['edition_label' => str_repeat('a', 256)], 'edition_label'],
]);

test('normalizes valid ISBNs before persisting', function (string $input, string $normalized): void {
    $reference = Reference::create([
        'type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'ISBN Work', 'isbn' => $input,
    ]);

    expect($reference->fresh()->isbn)->toBe($normalized);
})->with([
    'isbn13 with separators' => ['978-3-16-148410-0', '9783161484100'],
    'isbn10 with spaces' => ['0 306 40615 2', '0306406152'],
    'isbn10 lowercase check digit' => ['0-8044-2957-x', '080442957X'],
]);

test('rejects invalid ISBN checksums and prefixes', function (string $isbn): void {
    expect(fn () => Reference::create([
        'type' => ReferenceType::Book, 'status' => ReferenceStatus::Draft, 'title' => 'Bad ISBN', 'isbn' => $isbn,
    ]))->toThrow(ValidationException::class);
})->with(['9783161484101', '0306406153', '1234567890128', '978-3-16-148410-0!']);
