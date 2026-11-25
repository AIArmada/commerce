<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\Filament\OwnerScopedIds;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    if (app()->bound(OwnerResolverInterface::class)) {
        app()->forgetInstance(OwnerResolverInterface::class);
        app()->offsetUnset(OwnerResolverInterface::class);
    }

    Schema::dropIfExists('owner_scoped_id_fixtures');
    Schema::create('owner_scoped_id_fixtures', function (Blueprint $table): void {
        $table->id();
        $table->nullableMorphs('owner');
        $table->string('label');
        $table->timestamps();
    });
});

it('filters ids to the current owner and optionally includes global rows', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-scoped-ids-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-scoped-ids-b@example.com',
        'password' => 'secret',
    ]);

    $ownerARecord = OwnerContext::withOwner($ownerA, fn (): Model => OwnerScopedIdsFixture::query()->create([
        'label' => 'owner-a',
    ]));

    $ownerBRecord = OwnerContext::withOwner($ownerB, fn (): Model => OwnerScopedIdsFixture::query()->create([
        'label' => 'owner-b',
    ]));

    $globalRecord = OwnerContext::withOwner(null, fn (): Model => OwnerScopedIdsFixture::query()->create([
        'label' => 'global',
    ]));

    $ownerOnlyIds = OwnerContext::withOwner($ownerA, fn (): array => OwnerScopedIds::allowedIds(
        OwnerScopedIdsFixture::class,
        [(string) $ownerARecord->getKey(), (string) $ownerBRecord->getKey(), (string) $globalRecord->getKey()],
    ));

    $ownerAndGlobalIds = OwnerContext::withOwner($ownerA, fn (): array => OwnerScopedIds::allowedIds(
        OwnerScopedIdsFixture::class,
        [(string) $ownerARecord->getKey(), (string) $ownerBRecord->getKey(), (string) $globalRecord->getKey()],
        includeGlobal: true,
    ));

    $expectedOwnerAndGlobalIds = [(string) $ownerARecord->getKey(), (string) $globalRecord->getKey()];

    sort($ownerAndGlobalIds);
    sort($expectedOwnerAndGlobalIds);

    expect($ownerOnlyIds)->toEqual([(string) $ownerARecord->getKey()])
        ->and($ownerAndGlobalIds)->toEqual($expectedOwnerAndGlobalIds);
});

it('requires an owner context or explicit global context for id validation', function (): void {
    $owner = User::query()->create([
        'name' => 'Owner',
        'email' => 'owner-scoped-ids-global@example.com',
        'password' => 'secret',
    ]);

    $ownedRecord = OwnerContext::withOwner($owner, fn (): Model => OwnerScopedIdsFixture::query()->create([
        'label' => 'owned',
    ]));

    $globalRecord = OwnerContext::withOwner(null, fn (): Model => OwnerScopedIdsFixture::query()->create([
        'label' => 'global',
    ]));

    expect(fn (): array => OwnerScopedIds::allowedIds(
        OwnerScopedIdsFixture::class,
        [(string) $ownedRecord->getKey(), (string) $globalRecord->getKey()],
    ))->toThrow(NoCurrentOwnerException::class);

    $allowed = OwnerContext::withOwner(null, fn (): array => OwnerScopedIds::allowedIds(
        OwnerScopedIdsFixture::class,
        [(string) $ownedRecord->getKey(), (string) $globalRecord->getKey()],
    ));

    expect($allowed)->toEqual([(string) $globalRecord->getKey()]);
});

it('throws a validation exception when submitted ids fall outside the current owner scope', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-scoped-ids-throw-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-scoped-ids-throw-b@example.com',
        'password' => 'secret',
    ]);

    $foreignRecord = OwnerContext::withOwner($ownerB, fn (): Model => OwnerScopedIdsFixture::query()->create([
        'label' => 'foreign',
    ]));

    OwnerContext::withOwner($ownerA, function () use ($foreignRecord): void {
        expect(fn (): array => OwnerScopedIds::ensureAllowed(
            'fixtures',
            OwnerScopedIdsFixture::class,
            [(string) $foreignRecord->getKey()],
        ))->toThrow(ValidationException::class);
    });
});

final class OwnerScopedIdsFixture extends Model
{
    use HasOwner;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_scoped_id_fixtures';
    }
}

it('respects a pinned owner config without ambient context', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Pinned Ids A',
        'email' => 'pinned-ids-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Pinned Ids B',
        'email' => 'pinned-ids-b@example.com',
        'password' => 'secret',
    ]);

    $ownerARecord = OwnerContext::withOwner($ownerA, fn (): Model => OwnerScopedIdsFixture::query()->create(['label' => 'pinned-a']));
    $ownerBRecord = OwnerContext::withOwner($ownerB, fn (): Model => OwnerScopedIdsFixture::query()->create(['label' => 'pinned-b']));

    OwnerScopedIdsPinnedFixture::$owner = $ownerA;

    try {
        $allowed = OwnerScopedIds::allowedIds(
            OwnerScopedIdsPinnedFixture::class,
            [(string) $ownerARecord->getKey(), (string) $ownerBRecord->getKey()],
        );

        expect($allowed)->toEqual([(string) $ownerARecord->getKey()]);
    } finally {
        OwnerScopedIdsPinnedFixture::$owner = null;
    }
});

it('suppresses include-global matching through the scope override', function (): void {
    $owner = User::query()->create([
        'name' => 'Override Ids Owner',
        'email' => 'override-ids-owner@example.com',
        'password' => 'secret',
    ]);

    $ownedRecord = OwnerContext::withOwner($owner, fn (): Model => OwnerScopedIdsFixture::query()->create(['label' => 'override-owned']));
    $globalRecord = OwnerContext::withOwner(null, fn (): Model => OwnerScopedIdsFixture::query()->create(['label' => 'override-global']));

    $ids = [(string) $ownedRecord->getKey(), (string) $globalRecord->getKey()];

    $withGlobal = OwnerContext::withOwner($owner, fn (): array => OwnerScopedIds::allowedIds(
        OwnerScopedIdsFixture::class,
        $ids,
        includeGlobal: true,
    ));

    $suppressed = OwnerContext::withOwner($owner, fn (): array => OwnerScopeOverride::withoutIncludeGlobal(
        fn (): array => OwnerScopedIds::allowedIds(
            OwnerScopedIdsFixture::class,
            $ids,
            includeGlobal: true,
        ),
    ));

    sort($withGlobal);

    $expectedWithGlobal = [(string) $ownedRecord->getKey(), (string) $globalRecord->getKey()];
    sort($expectedWithGlobal);

    expect($withGlobal)->toEqual($expectedWithGlobal)
        ->and($suppressed)->toEqual([(string) $ownedRecord->getKey()]);
});

final class OwnerScopedIdsPinnedFixture extends Model
{
    use HasOwner;

    public static ?Model $owner = null;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_scoped_id_fixtures';
    }

    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return new OwnerScopeConfig(enabled: true, owner: EloquentRecordReference::fromModel(self::$owner));
    }
}
