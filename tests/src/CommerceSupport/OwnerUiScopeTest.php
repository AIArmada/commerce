<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    if (app()->bound(OwnerResolverInterface::class)) {
        app()->forgetInstance(OwnerResolverInterface::class);
        app()->offsetUnset(OwnerResolverInterface::class);
    }

    Schema::dropIfExists('owner_ui_scope_fixtures');
    Schema::create('owner_ui_scope_fixtures', function (Blueprint $table): void {
        $table->id();
        $table->nullableMorphs('owner');
        $table->string('label');
        $table->timestamps();
    });
});

it('applies owner ui scoping for owner and explicit global contexts', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-ui-scope-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-ui-scope-b@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($ownerA, fn (): Model => OwnerUiScopeFixture::query()->create([
        'label' => 'owner-a',
    ]));

    OwnerContext::withOwner($ownerB, fn (): Model => OwnerUiScopeFixture::query()->create([
        'label' => 'owner-b',
    ]));

    OwnerContext::withOwner(null, fn (): Model => OwnerUiScopeFixture::query()->create([
        'label' => 'global',
    ]));

    $ownerLabels = OwnerContext::withOwner($ownerA, fn (): array => OwnerUiScope::apply(OwnerUiScopeFixture::query(), includeGlobal: true)
        ->orderBy('label')
        ->pluck('label')
        ->all());

    $globalLabels = OwnerContext::withOwner(null, fn (): array => OwnerUiScope::apply(OwnerUiScopeFixture::query())
        ->orderBy('label')
        ->pluck('label')
        ->all());

    expect($ownerLabels)->toEqual(['global', 'owner-a'])
        ->and($globalLabels)->toEqual(['global']);
});

it('distinguishes access from mutation for global rows and fails closed for foreign record helpers', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-ui-scope-access-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-ui-scope-access-b@example.com',
        'password' => 'secret',
    ]);

    $ownerAPrimary = OwnerContext::withOwner($ownerA, fn (): Model => OwnerUiScopeFixture::query()->create([
        'label' => 'owner-a-primary',
    ]));

    $ownerASecondary = OwnerContext::withOwner($ownerA, fn (): Model => OwnerUiScopeFixture::query()->create([
        'label' => 'owner-a-secondary',
    ]));

    $ownerBRecord = OwnerContext::withOwner($ownerB, fn (): Model => OwnerUiScopeFixture::query()->create([
        'label' => 'owner-b',
    ]));

    $globalRecord = OwnerContext::withOwner(null, fn (): Model => OwnerUiScopeFixture::query()->create([
        'label' => 'global',
    ]))->refresh();

    OwnerContext::withOwner($ownerA, function () use ($globalRecord, $ownerAPrimary, $ownerASecondary, $ownerBRecord): void {
        expect(OwnerUiScope::canAccessRecord($globalRecord, includeGlobal: true))->toBeTrue()
            ->and(OwnerUiScope::canMutateRecord($globalRecord))->toBeFalse()
            ->and(OwnerUiScope::findForRecordOwner(OwnerUiScopeFixture::class, $ownerAPrimary, $ownerASecondary->getKey())?->getKey())
            ->toBe($ownerASecondary->getKey())
            ->and(OwnerUiScope::applyForRecordOwner(OwnerUiScopeFixture::query(), $ownerBRecord)->count())
            ->toBe(0);
    });
});

final class OwnerUiScopeFixture extends Model
{
    use HasOwner;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_ui_scope_fixtures';
    }
}

it('respects a pinned owner config without ambient context', function (): void {
    $owner = User::query()->create([
        'name' => 'Pinned Owner',
        'email' => 'owner-ui-scope-pinned@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($owner, fn (): Model => OwnerUiScopeFixture::query()->create([
        'label' => 'pinned-owned',
    ]));

    OwnerContext::withOwner(null, fn (): Model => OwnerUiScopeFixture::query()->create([
        'label' => 'global',
    ]));

    OwnerUiScopePinnedFixture::$owner = $owner;

    try {
        expect(OwnerUiScope::resolveOwner(OwnerUiScopePinnedFixture::class)?->is($owner))->toBeTrue()
            ->and(OwnerUiScope::canCreate(OwnerUiScopePinnedFixture::class))->toBeTrue()
            ->and(OwnerUiScope::apply(OwnerUiScopePinnedFixture::query())->count())->toBe(1)
            ->and(OwnerUiScope::apply(OwnerUiScopePinnedFixture::query())->pluck('label')->all())->toBe(['pinned-owned']);
    } finally {
        OwnerUiScopePinnedFixture::$owner = null;
    }
});

it('suppresses include-global matching through the scope override', function (): void {
    $owner = User::query()->create([
        'name' => 'Override Owner',
        'email' => 'owner-ui-scope-override@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($owner, fn (): Model => OwnerUiScopeFixture::query()->create([
        'label' => 'override-owned',
    ]));

    OwnerContext::withOwner(null, fn (): Model => OwnerUiScopeFixture::query()->create([
        'label' => 'override-global',
    ]));

    OwnerContext::withOwner($owner, function (): void {
        $withGlobal = OwnerUiScope::apply(OwnerUiScopeFixture::query(), includeGlobal: true)->count();

        $suppressed = OwnerScopeOverride::withoutIncludeGlobal(
            fn (): int => OwnerUiScope::apply(OwnerUiScopeFixture::query(), includeGlobal: true)->count(),
        );

        expect($withGlobal)->toBe(2)
            ->and($suppressed)->toBe(1);
    });
});

final class OwnerUiScopePinnedFixture extends Model
{
    use HasOwner;

    public static ?Model $owner = null;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_ui_scope_fixtures';
    }

    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return new OwnerScopeConfig(enabled: true, owner: EloquentRecordReference::fromModel(self::$owner));
    }
}
