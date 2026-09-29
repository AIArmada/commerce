<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Filament\Pages\Page;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Locked;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    Schema::dropIfExists('rm_guard_records');
    Schema::dropIfExists('rm_guard_plains');

    Schema::create('rm_guard_records', function (Blueprint $table): void {
        $table->id();
        $table->nullableMorphs('owner');
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->string('label');
        $table->timestamps();
    });

    Schema::create('rm_guard_plains', function (Blueprint $table): void {
        $table->id();
        $table->string('label');
        $table->timestamps();
    });

    RelationGuardPinnedFixture::$pinnedOwner = null;

    $views = sys_get_temp_dir() . '/commerce-rm-guard-views';

    if (! is_dir($views)) {
        mkdir($views, 0777, true);
    }

    file_put_contents($views . '/rm-guard-stub.blade.php', '<div>relation guard stub</div>');
    view()->addLocation($views);
});

function rmGuardOwner(string $name): User
{
    return User::query()->create([
        'name' => $name,
        'email' => Str::slug($name) . '@example.com',
        'password' => 'secret',
    ]);
}

function rmGuardRecord(User $owner, string $label = 'guarded'): RelationGuardRecordFixture
{
    return OwnerContext::withOwner(
        $owner,
        fn () => RelationGuardRecordFixture::query()->create(['label' => $label]),
    );
}

function rmGuardAbortStatus(callable $callback): ?int
{
    try {
        $callback();
    } catch (HttpException $e) {
        return $e->getStatusCode();
    }

    return null;
}

it('stamps a visible owned ownerRecord at mount and keeps it on hydrate', function (): void {
    $owner = rmGuardOwner('RM Stamp Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $manager = app(RelationGuardStubManager::class);
    $manager->ownerRecord = rmGuardRecord($owner);

    $manager->mountVerifiesRecordOwnerContext();

    expect($manager->recordOwnerStamp)->toBeString();

    $manager->hydrateVerifiesRecordOwnerContext();

    expect($manager->ownerRecord)->toBeInstanceOf(Model::class)
        ->and($manager->recordOwnerStamp)->toBeString();
});

it('aborts with 403 at mount for a cross-owner ownerRecord', function (): void {
    $ownerA = rmGuardOwner('RM Cross Owner A');
    $ownerB = rmGuardOwner('RM Cross Owner B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $manager = app(RelationGuardStubManager::class);
    $manager->ownerRecord = rmGuardRecord($ownerA);

    expect(rmGuardAbortStatus(fn () => $manager->mountVerifiesRecordOwnerContext()))->toBe(403);
});

it('aborts with 403 on hydrate after an owner switch', function (): void {
    $ownerA = rmGuardOwner('RM Switch A');
    $ownerB = rmGuardOwner('RM Switch B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $manager = app(RelationGuardStubManager::class);
    $manager->ownerRecord = rmGuardRecord($ownerA);
    $manager->mountVerifiesRecordOwnerContext();

    expect($manager->recordOwnerStamp)->toBeString();

    $status = OwnerContext::withOwner(
        $ownerB,
        fn () => rmGuardAbortStatus(fn () => $manager->hydrateVerifiesRecordOwnerContext()),
    );

    expect($status)->toBe(403);
});

it('aborts with 403 when the owner row is deleted after mount', function (): void {
    $owner = rmGuardOwner('RM Delete Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $record = rmGuardRecord($owner);

    $manager = app(RelationGuardStubManager::class);
    $manager->ownerRecord = $record;
    $manager->mountVerifiesRecordOwnerContext();

    DB::table('rm_guard_records')->where('id', $record->getKey())->delete();

    expect(rmGuardAbortStatus(fn () => $manager->hydrateVerifiesRecordOwnerContext()))->toBe(403);
});

it('skips silently when owner scoping is disabled', function (): void {
    $owner = rmGuardOwner('RM Disabled Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $record = OwnerContext::withOwner(
        $owner,
        fn () => RelationGuardDisabledFixture::query()->create(['label' => 'disabled']),
    );

    $manager = app(RelationGuardStubManager::class);
    $manager->ownerRecord = $record;

    $manager->mountVerifiesRecordOwnerContext();
    $manager->hydrateVerifiesRecordOwnerContext();

    expect($manager->recordOwnerStamp)->toBeNull();
});

it('skips silently for a non-owner-scoped ownerRecord', function (): void {
    $owner = rmGuardOwner('RM Plain Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $manager = app(RelationGuardStubManager::class);
    $manager->ownerRecord = RelationGuardPlainFixture::query()->create(['label' => 'plain']);

    $manager->mountVerifiesRecordOwnerContext();
    $manager->hydrateVerifiesRecordOwnerContext();

    expect($manager->recordOwnerStamp)->toBeNull();
});

it('pins visibility to a configured fixed owner across ambient switches', function (): void {
    $ownerA = rmGuardOwner('RM Pin A');
    $ownerB = rmGuardOwner('RM Pin B');
    RelationGuardPinnedFixture::$pinnedOwner = $ownerA;
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $record = OwnerContext::withOwner(
        $ownerA,
        fn () => RelationGuardPinnedFixture::query()->create(['label' => 'pinned']),
    );

    $manager = app(RelationGuardStubManager::class);
    $manager->ownerRecord = $record;
    $manager->mountVerifiesRecordOwnerContext();

    expect($manager->recordOwnerStamp)->toBeString();

    $manager->hydrateVerifiesRecordOwnerContext();

    expect($manager->recordOwnerStamp)->toBeString();
});

it('aborts with 403 through a real Livewire request cycle after an owner switch', function (): void {
    $ownerA = rmGuardOwner('RM Cycle A');
    $ownerB = rmGuardOwner('RM Cycle B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $test = Livewire::test(RelationGuardStubManager::class, [
        'ownerRecord' => rmGuardRecord($ownerA),
        'pageClass' => Page::class,
    ]);

    // Mount hook ran automatically and stamped the visible owner record.
    expect($test->get('recordOwnerStamp'))->toBeString();

    $test->call('$refresh');

    expect($test->get('ownerRecord'))->not->toBeNull();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $test->call('$refresh')->assertStatus(403);
});

it('aborts with 403 on a real Livewire mount with a cross-owner record', function (): void {
    $ownerA = rmGuardOwner('RM Mount A');
    $ownerB = rmGuardOwner('RM Mount B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $record = rmGuardRecord($ownerA);

    $test = Livewire::test(RelationGuardStubManager::class, [
        'ownerRecord' => $record,
        'pageClass' => Page::class,
    ]);

    $test->assertStatus(403);
});

it('fails closed with 404 on refresh when the row is deleted mid-cycle', function (): void {
    $owner = rmGuardOwner('RM Mid Cycle Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $record = rmGuardRecord($owner);

    $test = Livewire::test(RelationGuardStubManager::class, [
        'ownerRecord' => $record,
        'pageClass' => Page::class,
    ]);

    expect($test->get('ownerRecord'))->not->toBeNull();

    DB::table('rm_guard_records')->where('id', $record->getKey())->delete();

    // Fail-closed via 404 here: the stub uses the base `canViewForRecord()`,
    // which resolves the owner record, so Filament's own
    // `hydrateCanAuthorizeAccess` hook (running first) surfaces
    // `ModelNotFoundException` for the deleted row. Managers whose
    // authorization never touches the record reach this guard instead and
    // abort 403. Either way the stale request is blocked.
    $test->call('$refresh')->assertStatus(404);
});

it('aborts with 403 on refresh when deletion bypasses record-touching authorization', function (): void {
    $owner = rmGuardOwner('RM No Touch Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $record = rmGuardRecord($owner);

    $test = Livewire::test(RelationGuardNoTouchStubManager::class, [
        'ownerRecord' => $record,
        'pageClass' => Page::class,
    ]);

    expect($test->get('ownerRecord'))->not->toBeNull();

    DB::table('rm_guard_records')->where('id', $record->getKey())->delete();

    // This manager's authorization never resolves the owner record, so
    // Filament's auth hook cannot 404 first: the guard converts the
    // missing row to its own 403. Still fail-closed.
    $test->call('$refresh')->assertStatus(403);
});

it('exposes Livewire-conventional hook names and a locked stamp', function (): void {
    $manager = app(RelationGuardStubManager::class);

    expect(method_exists($manager, 'mountVerifiesRecordOwnerContext'))->toBeTrue()
        ->and(method_exists($manager, 'hydrateVerifiesRecordOwnerContext'))->toBeTrue();

    $stamp = new ReflectionProperty($manager, 'recordOwnerStamp');

    expect($stamp->getAttributes(Locked::class))->not->toBeEmpty();
});

class RelationGuardStubManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'children';

    public function render(): View
    {
        return view('rm-guard-stub');
    }
}

class RelationGuardNoTouchStubManager extends RelationGuardStubManager
{
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }
}

final class RelationGuardRecordFixture extends Model
{
    use HasOwner;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'rm_guard_records';
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return new OwnerScopeConfig(enabled: true, includeGlobal: false);
    }
}

final class RelationGuardDisabledFixture extends Model
{
    use HasOwner;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'rm_guard_records';
    }

    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return new OwnerScopeConfig(enabled: false);
    }
}

final class RelationGuardPinnedFixture extends Model
{
    use HasOwner;

    public static ?Model $pinnedOwner = null;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'rm_guard_records';
    }

    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return new OwnerScopeConfig(enabled: true, includeGlobal: false, owner: self::$pinnedOwner);
    }
}

final class RelationGuardPlainFixture extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return 'rm_guard_plains';
    }
}
