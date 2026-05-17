<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\Filament\OwnerScopedIds;
use AIArmada\CommerceSupport\Support\OwnerContext;
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

it('treats missing owner context as global-only for id validation', function (): void {
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

    $allowed = OwnerScopedIds::allowedIds(
        OwnerScopedIdsFixture::class,
        [(string) $ownedRecord->getKey(), (string) $globalRecord->getKey()],
    );

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
