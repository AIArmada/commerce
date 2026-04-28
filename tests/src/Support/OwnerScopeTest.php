<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Contracts\OwnerScopeConfigurable;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('owner_scope_fixtures');
    Schema::create('owner_scope_fixtures', function (Blueprint $table): void {
        $table->id();
        $table->nullableMorphs('owner');
        $table->string('owner_scope')->default('global');
        $table->string('label');
        $table->timestamps();
    });
});

afterEach(function (): void {
    OwnerContext::clearOverride();
});

it('applies the owner scope and supports explicit opt-out', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-scope@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b-scope@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($ownerA, fn () => OwnerScopedFixture::query()->create([
        'label' => 'owner-a',
    ]));

    OwnerContext::withOwner($ownerB, fn () => OwnerScopedFixture::query()->create([
        'label' => 'owner-b',
    ]));

    OwnerContext::withOwner(null, function (): void {
        OwnerScopedFixture::query()->create([
            'label' => 'global',
        ]);
    });

    OwnerContext::withOwner($ownerA, function (): void {
        $scoped = OwnerScopedFixture::query()
            ->orderBy('label')
            ->pluck('label')
            ->all();

        expect($scoped)->toBe(['owner-a']);

        $unscoped = OwnerScopedFixture::query()
            ->withoutOwnerScope()
            ->orderBy('label')
            ->pluck('label')
            ->all();

        expect($unscoped)->toBe(['global', 'owner-a', 'owner-b']);
    });

    OwnerContext::withOwner($ownerB, function (): void {
        $forOwner = OwnerScopedFixture::query()
            ->withoutOwnerScope()
            ->forOwner()
            ->orderBy('label')
            ->pluck('label')
            ->all();

        expect($forOwner)->toBe(['owner-b']);
    });
});

it('scopes query builder owner columns with optional include-global', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-query@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b-query@example.com',
        'password' => 'secret',
    ]);

    DB::table('owner_scope_fixtures')->insert([
        [
            'label' => 'owner-a',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
        ],
        [
            'label' => 'owner-b',
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
        ],
        [
            'label' => 'global',
            'owner_type' => null,
            'owner_id' => null,
        ],
    ]);

    $scoped = OwnerQuery::applyToQueryBuilder(DB::table('owner_scope_fixtures'), $ownerB, true)
        ->orderBy('label')
        ->pluck('label')
        ->all();

    expect($scoped)->toBe(['global', 'owner-b']);
});

it('defaults to excluding global rows for models without explicit config', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-default-include-global@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($ownerA, fn () => OwnerScopedNoConfigFixture::query()->create([
        'label' => 'owner-a',
    ]));

    OwnerContext::withOwner(null, function (): void {
        OwnerScopedNoConfigFixture::query()->create([
            'label' => 'global',
        ]);
    });

    $scopedDefault = OwnerScopedNoConfigFixture::query()
        ->withoutOwnerScope()
        ->forOwner($ownerA)
        ->orderBy('label')
        ->pluck('label')
        ->all();

    expect($scopedDefault)->toBe(['owner-a']);

    $scopedWithGlobal = OwnerScopedNoConfigFixture::query()
        ->withoutOwnerScope()
        ->forOwner($ownerA, true)
        ->orderBy('label')
        ->pluck('label')
        ->all();

    expect($scopedWithGlobal)->toBe(['global', 'owner-a']);
});

it('fails fast when owner scope is used without an owner or explicit global context', function (): void {
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver(null));
    OwnerContext::clearOverride();

    expect(fn () => OwnerScopedFixture::query()->count())
        ->toThrow(RuntimeException::class, 'requires an owner context');
});

it('treats null owner overrides as explicit global context', function (): void {
    OwnerContext::withOwner(null, function (): void {
        OwnerScopedFixture::query()->create([
            'label' => 'global',
        ]);
    });

    expect(OwnerContext::withOwner(null, fn () => OwnerScopedFixture::query()->pluck('label')->all()))
        ->toBe(['global']);
});

it('restores nested owner overrides', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-nested-context@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b-nested-context@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($ownerA, function () use ($ownerA, $ownerB): void {
        expect(OwnerContext::resolve()?->is($ownerA))->toBeTrue();

        OwnerContext::withOwner(null, function () use ($ownerB): void {
            expect(OwnerContext::isExplicitGlobal())->toBeTrue();

            OwnerContext::withOwner($ownerB, function () use ($ownerB): void {
                expect(OwnerContext::resolve()?->is($ownerB))->toBeTrue();
            });

            expect(OwnerContext::isExplicitGlobal())->toBeTrue();
        });

        expect(OwnerContext::resolve()?->is($ownerA))->toBeTrue();
    });

    expect(OwnerContext::hasOverride())->toBeFalse();
});

it('protects global rows from owner-context writes', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-global-write@example.com',
        'password' => 'secret',
    ]);

    $global = OwnerContext::withOwner(null, fn () => OwnerScopedFixture::query()->create([
        'label' => 'global',
    ]));

    OwnerContext::withOwner($ownerA, function () use ($global): void {
        $global->label = 'changed';

        expect(fn () => $global->save())->toThrow(AuthorizationException::class);
        expect(fn () => $global->delete())->toThrow(AuthorizationException::class);
    });

    OwnerContext::withOwner(null, function () use ($global): void {
        $global->label = 'changed';
        $global->save();

        expect($global->fresh()?->label)->toBe('changed');
    });
});

it('computes hidden owner scope keys from owner columns', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-owner-scope-key@example.com',
        'password' => 'secret',
    ]);

    $owned = OwnerContext::withOwner($ownerA, fn () => OwnerScopedKeyFixture::query()->create([
        'label' => 'owned',
    ]));

    $global = OwnerContext::withOwner(null, fn () => OwnerScopedKeyFixture::query()->create([
        'label' => 'global',
    ]));

    expect($owned->getAttribute('owner_scope'))->toBeString()->not->toBe('global')
        ->and($global->getAttribute('owner_scope'))->toBe('global');
});

final class OwnerScopedFixture extends Model implements OwnerScopeConfigurable
{
    use HasOwner;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_scope_fixtures';
    }

    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return new OwnerScopeConfig(enabled: true, includeGlobal: false);
    }
}

final class OwnerScopedKeyFixture extends Model implements OwnerScopeConfigurable
{
    use HasOwner;
    use HasOwnerScopeKey;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_scope_fixtures';
    }

    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return new OwnerScopeConfig(enabled: true, includeGlobal: false);
    }
}

final class OwnerScopedNoConfigFixture extends Model
{
    use HasOwner;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_scope_fixtures';
    }
}

// ---------------------------------------------------------------------------
// Custom owner column tests
// ---------------------------------------------------------------------------

it('instance helpers honour custom owner column names', function (): void {
    Schema::dropIfExists('custom_col_fixtures');
    Schema::create('custom_col_fixtures', function (Blueprint $table): void {
        $table->id();
        $table->nullableMorphs('tenant');
        $table->string('label');
        $table->timestamps();
    });

    $owner = User::query()->create([
        'name' => 'Custom Col Owner',
        'email' => 'custom-col-owner@example.com',
        'password' => 'secret',
    ]);

    // assignOwner on a new (unsaved) model uses custom columns
    $unsaved = new CustomColFixture(['label' => 'new']);
    $unsaved->assignOwner($owner);

    expect($unsaved->hasOwner())->toBeTrue()
        ->and($unsaved->isGlobal())->toBeFalse()
        ->and($unsaved->belongsToOwner($owner))->toBeTrue();

    // Persist via normal owner-context creation path
    $model = OwnerContext::withOwner($owner, fn () => CustomColFixture::query()->create(['label' => 'owned']));

    expect($model->hasOwner())->toBeTrue()
        ->and($model->isGlobal())->toBeFalse()
        ->and($model->belongsToOwner($owner))->toBeTrue();

    // removeOwner on a persisted owned record must throw — strict immutability
    expect(fn () => $model->removeOwner())
        ->toThrow(InvalidArgumentException::class, 'Owner cannot be removed from a persisted');

    // removeOwner on an unsaved model is still allowed
    $unsaved2 = new CustomColFixture(['label' => 'new2']);
    $unsaved2->assignOwner($owner);
    $unsaved2->removeOwner();
    expect($unsaved2->isGlobal())->toBeTrue();

    Schema::dropIfExists('custom_col_fixtures');
});

it('owner() relation honours custom owner column names', function (): void {
    Schema::dropIfExists('custom_col_fixtures');
    Schema::create('custom_col_fixtures', function (Blueprint $table): void {
        $table->id();
        $table->nullableMorphs('tenant');
        $table->string('label');
        $table->timestamps();
    });

    $owner = User::query()->create([
        'name' => 'Relation Owner',
        'email' => 'relation-owner@example.com',
        'password' => 'secret',
    ]);

    $model = OwnerContext::withOwner($owner, fn () => CustomColFixture::query()->create(['label' => 'rel-test']));

    $resolved = $model->owner;

    expect($resolved)->not->toBeNull()
        ->and($resolved->getKey())->toBe($owner->getKey());

    Schema::dropIfExists('custom_col_fixtures');
});

final class CustomColFixture extends Model
{
    use HasOwner;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'custom_col_fixtures';
    }

    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return new OwnerScopeConfig(
            enabled: true,
            includeGlobal: false,
            ownerTypeColumn: 'tenant_type',
            ownerIdColumn: 'tenant_id',
        );
    }
}
