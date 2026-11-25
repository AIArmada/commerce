<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\CommerceSupport\Support\OwnerUniqueRule;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

beforeEach(function (): void {
    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?RecordReference
        {
            return null;
        }
    });

    Schema::dropIfExists('owner_unique_rule_fixtures');
    Schema::create('owner_unique_rule_fixtures', function (Blueprint $table): void {
        $table->id();
        $table->nullableMorphs('owner');
        $table->string('code');
        $table->timestamps();
    });
});

function ownerUniqueRule(): Unique
{
    return OwnerUniqueRule::scopeToOwner(Rule::unique('owner_unique_rule_fixtures', 'code'), OwnerUniqueRuleFixture::class);
}

it('fails closed when no owner context or explicit global context exists', function (): void {
    expect(fn (): Unique => ownerUniqueRule())->toThrow(NoCurrentOwnerException::class);
});

it('scopes uniqueness to the resolved owner', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Unique Owner A',
        'email' => 'owner-unique-rule-a@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($ownerA, fn (): Model => OwnerUniqueRuleFixture::query()->create(['code' => 'TAKEN']));

    $rule = OwnerContext::withOwner($ownerA, fn (): Unique => ownerUniqueRule());

    expect(Validator::make(['code' => 'TAKEN'], ['code' => $rule])->fails())->toBeTrue()
        ->and(Validator::make(['code' => 'FREE'], ['code' => $rule])->passes())->toBeTrue();
});

it('scopes uniqueness to global rows in explicit global context', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Unique Owner B',
        'email' => 'owner-unique-rule-b@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner(null, fn (): Model => OwnerUniqueRuleFixture::query()->create(['code' => 'GLOBAL-TAKEN']));
    OwnerContext::withOwner($ownerA, fn (): Model => OwnerUniqueRuleFixture::query()->create(['code' => 'OWNED-TAKEN']));

    $globalRule = OwnerContext::withOwner(null, fn (): Unique => ownerUniqueRule());

    expect(Validator::make(['code' => 'GLOBAL-TAKEN'], ['code' => $globalRule])->fails())->toBeTrue()
        ->and(Validator::make(['code' => 'OWNED-TAKEN'], ['code' => $globalRule])->passes())->toBeTrue();
});

it('honours include-global matching when configured', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Unique Owner C',
        'email' => 'owner-unique-rule-c@example.com',
        'password' => 'secret',
    ]);

    OwnerUniqueRuleIncludeGlobalFixture::$owner = $ownerA;
    OwnerUniqueRuleIncludeGlobalFixture::$includeGlobal = true;

    OwnerContext::withOwner(null, fn (): Model => OwnerUniqueRuleIncludeGlobalFixture::query()->create(['code' => 'GLOBAL-C']));

    $rule = OwnerContext::withOwner($ownerA, fn (): Unique => OwnerUniqueRule::scopeToOwner(
        Rule::unique('owner_unique_rule_fixtures', 'code'),
        OwnerUniqueRuleIncludeGlobalFixture::class,
    ));

    expect(Validator::make(['code' => 'GLOBAL-C'], ['code' => $rule])->fails())->toBeTrue();
});

it('suppresses include-global matching through the scope override', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Unique Owner D',
        'email' => 'owner-unique-rule-d@example.com',
        'password' => 'secret',
    ]);

    OwnerUniqueRuleIncludeGlobalFixture::$owner = null;
    OwnerUniqueRuleIncludeGlobalFixture::$includeGlobal = true;

    OwnerContext::withOwner(null, fn (): Model => OwnerUniqueRuleIncludeGlobalFixture::query()->create(['code' => 'GLOBAL-D']));

    $rule = OwnerContext::withOwner($ownerA, fn (): Unique => OwnerScopeOverride::withoutIncludeGlobal(
        fn (): Unique => OwnerUniqueRule::scopeToOwner(
            Rule::unique('owner_unique_rule_fixtures', 'code'),
            OwnerUniqueRuleIncludeGlobalFixture::class,
        ),
    ));

    expect(Validator::make(['code' => 'GLOBAL-D'], ['code' => $rule])->passes())->toBeTrue();
});

it('scopes uniqueness to a pinned owner without ambient context', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Unique Owner E',
        'email' => 'owner-unique-rule-e@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($ownerA, fn (): Model => OwnerUniqueRuleFixture::query()->create(['code' => 'PINNED']));

    OwnerUniqueRuleFixture::$pinnedOwner = $ownerA;

    try {
        $rule = ownerUniqueRule();

        expect(Validator::make(['code' => 'PINNED'], ['code' => $rule])->fails())->toBeTrue()
            ->and(Validator::make(['code' => 'PINNED-FREE'], ['code' => $rule])->passes())->toBeTrue();
    } finally {
        OwnerUniqueRuleFixture::$pinnedOwner = null;
    }
});

it('leaves the rule unscoped when owner scoping is disabled', function (): void {
    OwnerUniqueRuleDisabledFixture::query()->create(['code' => 'DISABLED-TAKEN']);

    $rule = OwnerUniqueRule::scopeToOwner(
        Rule::unique('owner_unique_rule_fixtures', 'code'),
        OwnerUniqueRuleDisabledFixture::class,
    );

    expect(Validator::make(['code' => 'DISABLED-TAKEN'], ['code' => $rule])->fails())->toBeTrue();
});

final class OwnerUniqueRuleFixture extends Model
{
    use HasOwner;

    public static ?Model $pinnedOwner = null;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_unique_rule_fixtures';
    }

    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return new OwnerScopeConfig(enabled: true, includeGlobal: false, owner: EloquentRecordReference::fromModel(self::$pinnedOwner));
    }
}

final class OwnerUniqueRuleIncludeGlobalFixture extends Model
{
    use HasOwner;

    public static ?Model $owner = null;

    public static bool $includeGlobal = false;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_unique_rule_fixtures';
    }

    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return new OwnerScopeConfig(enabled: true, includeGlobal: self::$includeGlobal, owner: EloquentRecordReference::fromModel(self::$owner));
    }
}

final class OwnerUniqueRuleDisabledFixture extends Model
{
    use HasOwner;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_unique_rule_fixtures';
    }

    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return new OwnerScopeConfig(enabled: false);
    }
}
