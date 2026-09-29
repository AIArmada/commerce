<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRecordOwnerContext;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function (): void {
    Schema::dropIfExists('owner_guard_records');
    Schema::dropIfExists('owner_guard_plains');
    Schema::dropIfExists('owner_guard_tenants');

    Schema::create('owner_guard_records', function (Blueprint $table): void {
        $table->id();
        $table->nullableMorphs('owner');
        $table->string('label');
        $table->timestamps();
    });

    Schema::create('owner_guard_plains', function (Blueprint $table): void {
        $table->id();
        $table->string('label');
        $table->timestamps();
    });

    Schema::create('owner_guard_tenants', function (Blueprint $table): void {
        $table->id();
        $table->nullableMorphs('tenant');
        $table->string('label');
        $table->timestamps();
    });

    OwnerGuardPinnedFixture::$pinnedOwner = null;
});

function guardOwner(string $name): User
{
    return User::query()->create([
        'name' => $name,
        'email' => Str::slug($name) . '@example.com',
        'password' => 'secret',
    ]);
}

function guardRecord(User $owner, string $label = 'guarded'): OwnerGuardRecordFixture
{
    return OwnerContext::withOwner(
        $owner,
        fn () => OwnerGuardRecordFixture::query()->create(['label' => $label]),
    );
}

it('stamps a visible owned record at mount and keeps it on hydrate', function (): void {
    $owner = guardOwner('Stamp Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $component = app(OwnerGuardStubComponent::class);
    $component->record = guardRecord($owner);

    $component->mountVerifiesRecordOwnerContext();

    expect($component->record)->not->toBeNull()
        ->and($component->recordOwnerStamp)->toBeString();

    $component->hydrateVerifiesRecordOwnerContext();

    expect($component->record)->not->toBeNull()
        ->and($component->recordOwnerStamp)->toBeString();
});

it('produces deterministic stamps for identical context and record', function (): void {
    $owner = guardOwner('Deterministic Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $record = guardRecord($owner);

    $first = app(OwnerGuardStubComponent::class);
    $first->record = $record;
    $first->mountVerifiesRecordOwnerContext();

    $second = app(OwnerGuardStubComponent::class);
    $second->record = $record;
    $second->mountVerifiesRecordOwnerContext();

    expect($second->recordOwnerStamp)->toBe($first->recordOwnerStamp);
});

it('normalizes stamp scalars so int and string keys compare equal', function (): void {
    $owner = guardOwner('Scalar Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $component = app(OwnerGuardStubComponent::class);
    $component->record = guardRecord($owner);
    $component->mountVerifiesRecordOwnerContext();

    /** @var array{ctx: array{t: ?string, i: ?string}, rec: array{k: string, t: ?string, i: ?string}} $stamp */
    $stamp = json_decode((string) $component->recordOwnerStamp, true);

    expect($stamp['ctx']['i'])->toBe((string) $owner->getKey())
        ->and($stamp['rec']['k'])->toBe((string) $component->record->getKey())
        ->and($stamp['rec']['i'])->toBe((string) $owner->getKey());
});

it('fails closed at mount for a cross-owner record', function (): void {
    $ownerA = guardOwner('Cross Owner A');
    $ownerB = guardOwner('Cross Owner B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $component = app(OwnerGuardStubComponent::class);
    $component->record = guardRecord($ownerA);

    $component->mountVerifiesRecordOwnerContext();

    expect($component->record)->toBeNull()
        ->and($component->recordOwnerStamp)->toBeNull();
});

it('fails closed at mount when owner context is missing', function (): void {
    $owner = guardOwner('Missing Context Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver(null));

    $component = app(OwnerGuardStubComponent::class);
    $component->record = guardRecord($owner);

    $component->mountVerifiesRecordOwnerContext();

    expect($component->record)->toBeNull()
        ->and($component->recordOwnerStamp)->toBeNull();
});

it('fails closed on hydrate after an owner switch', function (): void {
    $ownerA = guardOwner('Switch Owner A');
    $ownerB = guardOwner('Switch Owner B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $component = app(OwnerGuardStubComponent::class);
    $component->record = guardRecord($ownerA);
    $component->mountVerifiesRecordOwnerContext();

    expect($component->record)->not->toBeNull();

    OwnerContext::withOwner($ownerB, function () use ($component): void {
        $component->hydrateVerifiesRecordOwnerContext();
    });

    expect($component->record)->toBeNull()
        ->and($component->recordOwnerStamp)->toBeNull();
});

it('fails closed on hydrate when the restored tuple no longer matches the stamp', function (): void {
    $ownerA = guardOwner('Reassign Owner A');
    $ownerB = guardOwner('Reassign Owner B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $component = app(OwnerGuardStubComponent::class);
    $component->record = guardRecord($ownerA);
    $component->mountVerifiesRecordOwnerContext();

    // Simulate restoration returning a record whose tuple changed in storage.
    $component->record = guardRecord($ownerB);

    $component->hydrateVerifiesRecordOwnerContext();

    expect($component->record)->toBeNull()
        ->and($component->recordOwnerStamp)->toBeNull();
});

it('fails closed on hydrate when the record no longer exists', function (): void {
    $owner = guardOwner('Deleted Record Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $record = guardRecord($owner);

    $component = app(OwnerGuardStubComponent::class);
    $component->record = $record;
    $component->mountVerifiesRecordOwnerContext();

    expect($component->record)->not->toBeNull();

    OwnerContext::withOwner($owner, function () use ($record): void {
        $record->delete();
    });

    $component->hydrateVerifiesRecordOwnerContext();

    expect($component->record)->toBeNull()
        ->and($component->recordOwnerStamp)->toBeNull();
});

it('fails closed on hydrate when the row was deleted by another process', function (): void {
    $owner = guardOwner('Externally Deleted Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $record = guardRecord($owner);

    $component = app(OwnerGuardStubComponent::class);
    $component->record = $record;
    $component->mountVerifiesRecordOwnerContext();

    expect($component->record)->not->toBeNull();

    // Bypass the model so the in-memory exists flag stays true while the row is gone.
    DB::table('owner_guard_records')->where('id', $record->getKey())->delete();

    $component->hydrateVerifiesRecordOwnerContext();

    expect($component->record)->toBeNull()
        ->and($component->recordOwnerStamp)->toBeNull();
});

it('fails closed on hydrate when no stamp exists', function (): void {
    $owner = guardOwner('Unstamped Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    // Record assigned without mount (e.g. a snapshot predating the guard).
    $component = app(OwnerGuardStubComponent::class);
    $component->record = guardRecord($owner);

    expect($component->recordOwnerStamp)->toBeNull();

    $component->hydrateVerifiesRecordOwnerContext();

    expect($component->record)->toBeNull()
        ->and($component->recordOwnerStamp)->toBeNull();
});

it('skips silently when owner scoping is disabled for the model', function (): void {
    $ownerA = guardOwner('Disabled Owner A');
    $ownerB = guardOwner('Disabled Owner B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $record = OwnerGuardDisabledFixture::query()->create(['label' => 'unscoped']);

    $component = app(OwnerGuardStubComponent::class);
    $component->record = $record;

    $component->mountVerifiesRecordOwnerContext();

    expect($component->record)->not->toBeNull()
        ->and($component->recordOwnerStamp)->toBeNull();

    OwnerContext::withOwner($ownerA, function () use ($component): void {
        $component->hydrateVerifiesRecordOwnerContext();
    });

    expect($component->record)->not->toBeNull();
});

it('skips silently for models without owner scoping', function (): void {
    $owner = guardOwner('Plain Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $component = app(OwnerGuardStubComponent::class);
    $component->record = OwnerGuardPlainFixture::query()->create(['label' => 'plain']);

    $component->mountVerifiesRecordOwnerContext();
    $component->hydrateVerifiesRecordOwnerContext();

    expect($component->record)->not->toBeNull()
        ->and($component->recordOwnerStamp)->toBeNull();
});

it('skips silently when the record is null', function (): void {
    $owner = guardOwner('Null Record Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $component = app(OwnerGuardStubComponent::class);

    $component->mountVerifiesRecordOwnerContext();
    $component->hydrateVerifiesRecordOwnerContext();

    expect($component->record)->toBeNull()
        ->and($component->recordOwnerStamp)->toBeNull();
});

it('skips silently for unsaved records', function (): void {
    $owner = guardOwner('Unsaved Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $component = app(OwnerGuardStubComponent::class);
    $component->record = new OwnerGuardRecordFixture(['label' => 'draft']);

    $component->mountVerifiesRecordOwnerContext();
    $component->hydrateVerifiesRecordOwnerContext();

    expect($component->record)->not->toBeNull()
        ->and($component->recordOwnerStamp)->toBeNull();
});

it('skips silently for components without a record prop', function (): void {
    $owner = guardOwner('No Prop Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $component = app(OwnerGuardNoRecordStubComponent::class);

    $component->mountVerifiesRecordOwnerContext();
    $component->hydrateVerifiesRecordOwnerContext();

    expect($component->recordOwnerStamp)->toBeNull();
});

it('keeps global records in explicit global context', function (): void {
    $owner = guardOwner('Global Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver(null));

    $record = OwnerContext::withOwner(
        null,
        fn () => OwnerGuardRecordFixture::query()->create(['label' => 'global']),
    );

    $component = app(OwnerGuardStubComponent::class);
    $component->record = $record;

    OwnerContext::withOwner(null, function () use ($component): void {
        $component->mountVerifiesRecordOwnerContext();
    });

    expect($component->record)->not->toBeNull();

    OwnerContext::withOwner(null, function () use ($component): void {
        $component->hydrateVerifiesRecordOwnerContext();
    });

    expect($component->record)->not->toBeNull();
});

it('keeps global records for owners when include-global is enabled', function (): void {
    $owner = guardOwner('Include Global Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $record = OwnerContext::withOwner(
        null,
        fn () => OwnerGuardGlobalFixture::query()->create(['label' => 'shared']),
    );

    $component = app(OwnerGuardStubComponent::class);
    $component->record = $record;

    $component->mountVerifiesRecordOwnerContext();

    expect($component->record)->not->toBeNull();

    $component->hydrateVerifiesRecordOwnerContext();

    expect($component->record)->not->toBeNull();
});

it('suppresses include-global inside an OwnerScopeOverride', function (): void {
    $owner = guardOwner('Override Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $record = OwnerContext::withOwner(
        null,
        fn () => OwnerGuardGlobalFixture::query()->create(['label' => 'override-global']),
    );

    // Without the override the global record stays visible.
    $kept = app(OwnerGuardStubComponent::class);
    $kept->record = $record;
    $kept->mountVerifiesRecordOwnerContext();

    expect($kept->record)->not->toBeNull();

    // Inside the override the same record fails closed, mirroring OwnerScope.
    $clearedAtMount = app(OwnerGuardStubComponent::class);
    $clearedAtMount->record = $record;

    OwnerScopeOverride::withoutIncludeGlobal(
        fn () => $clearedAtMount->mountVerifiesRecordOwnerContext(),
    );

    expect($clearedAtMount->record)->toBeNull()
        ->and($clearedAtMount->recordOwnerStamp)->toBeNull();

    $clearedAtHydrate = app(OwnerGuardStubComponent::class);
    $clearedAtHydrate->record = $record;
    $clearedAtHydrate->mountVerifiesRecordOwnerContext();

    expect($clearedAtHydrate->record)->not->toBeNull();

    OwnerScopeOverride::withoutIncludeGlobal(
        fn () => $clearedAtHydrate->hydrateVerifiesRecordOwnerContext(),
    );

    expect($clearedAtHydrate->record)->toBeNull()
        ->and($clearedAtHydrate->recordOwnerStamp)->toBeNull();
});

it('fails closed for global records when include-global is disabled', function (): void {
    $owner = guardOwner('Exclude Global Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $record = OwnerContext::withOwner(
        null,
        fn () => OwnerGuardRecordFixture::query()->create(['label' => 'hidden-global']),
    );

    $component = app(OwnerGuardStubComponent::class);
    $component->record = $record;

    $component->mountVerifiesRecordOwnerContext();

    expect($component->record)->toBeNull()
        ->and($component->recordOwnerStamp)->toBeNull();
});

it('honours custom owner column names', function (): void {
    $ownerA = guardOwner('Tenant Owner A');
    $ownerB = guardOwner('Tenant Owner B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $record = OwnerContext::withOwner(
        $ownerA,
        fn () => OwnerGuardTenantFixture::query()->create(['label' => 'tenant-row']),
    );

    $component = app(OwnerGuardStubComponent::class);
    $component->record = $record;
    $component->mountVerifiesRecordOwnerContext();

    expect($component->record)->not->toBeNull();

    OwnerContext::withOwner($ownerB, function () use ($component): void {
        $component->hydrateVerifiesRecordOwnerContext();
    });

    expect($component->record)->toBeNull();
});

it('fails closed when swapped to another same-owner record', function (): void {
    $owner = guardOwner('Same Owner Swap');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $component = app(OwnerGuardStubComponent::class);
    $component->record = guardRecord($owner, 'first');
    $component->mountVerifiesRecordOwnerContext();

    expect($component->recordOwnerStamp)->toBeString();

    // Same owner, different key: the stamped identity no longer matches.
    $component->record = guardRecord($owner, 'second');

    $component->hydrateVerifiesRecordOwnerContext();

    expect($component->record)->toBeNull()
        ->and($component->recordOwnerStamp)->toBeNull();
});

it('guards a custom prop name via ownerGuardedPropName', function (): void {
    $ownerA = guardOwner('Custom Prop A');
    $ownerB = guardOwner('Custom Prop B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $component = app(OwnerGuardEventStubComponent::class);
    $component->event = guardRecord($ownerA);
    $component->mountVerifiesRecordOwnerContext();

    expect($component->event)->not->toBeNull()
        ->and($component->recordOwnerStamp)->toBeString();

    OwnerContext::withOwner($ownerB, function () use ($component): void {
        $component->hydrateVerifiesRecordOwnerContext();
    });

    expect($component->event)->toBeNull()
        ->and($component->recordOwnerStamp)->toBeNull();
});

it('honours non-owner global scopes in the visibility check', function (): void {
    $owner = guardOwner('Scoped Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $visible = OwnerContext::withOwner(
        $owner,
        fn () => OwnerGuardScopedFixture::query()->create(['label' => 'visible']),
    );
    $archived = OwnerContext::withOwner(
        $owner,
        fn () => OwnerGuardScopedFixture::query()->create(['label' => 'archived']),
    );

    $kept = app(OwnerGuardStubComponent::class);
    $kept->record = $visible;
    $kept->mountVerifiesRecordOwnerContext();

    expect($kept->record)->not->toBeNull();

    $cleared = app(OwnerGuardStubComponent::class);
    $cleared->record = $archived;
    $cleared->mountVerifiesRecordOwnerContext();

    expect($cleared->record)->toBeNull()
        ->and($cleared->recordOwnerStamp)->toBeNull();
});

it('wires the guard through a real Livewire request cycle', function (): void {
    $ownerA = guardOwner('Cycle Owner A');
    $ownerB = guardOwner('Cycle Owner B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $record = guardRecord($ownerA);

    $test = Livewire::test(OwnerGuardRoundTripStub::class, ['record' => $record]);

    // Mount hook ran automatically and stamped the visible record.
    expect($test->get('recordOwnerStamp'))->toBeString()
        ->and($test->get('record'))->not->toBeNull();

    $test->call('ping');

    expect($test->get('record'))->not->toBeNull();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $test->call('$refresh');

    // Hydrate hook ran automatically and failed closed after the switch.
    expect($test->get('record'))->toBeNull()
        ->and($test->get('recordOwnerStamp'))->toBeNull();
});

it('restores fresh storage state through the snapshot pipeline', function (): void {
    $owner = guardOwner('Fresh Restore Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $record = guardRecord($owner, 'v1');

    $test = Livewire::test(OwnerGuardRoundTripStub::class, ['record' => $record]);

    OwnerGuardRecordFixture::query()->whereKey($record->getKey())->update(['label' => 'v2']);

    $test->call('ping');

    // A stale in-memory instance would still read v1.
    expect($test->get('record')->label)->toBe('v2');
});

it('renders empty when the row is deleted mid-cycle', function (): void {
    $owner = guardOwner('Mid Cycle Delete Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $record = guardRecord($owner);

    $test = Livewire::test(OwnerGuardRoundTripStub::class, ['record' => $record]);

    expect($test->get('record'))->not->toBeNull();

    DB::table('owner_guard_records')->where('id', $record->getKey())->delete();

    $test->call('$refresh');

    expect($test->get('record'))->toBeNull()
        ->and($test->get('recordOwnerStamp'))->toBeNull();
});

it('rejects client-side updates to locked guard props', function (): void {
    $owner = guardOwner('Locked Props Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $test = Livewire::test(OwnerGuardRoundTripStub::class, ['record' => guardRecord($owner)]);

    expect(fn () => $test->set('recordOwnerStamp', 'tampered'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('verifies the record when a lazy mount resumes', function (): void {
    $ownerA = guardOwner('Lazy Owner A');
    $ownerB = guardOwner('Lazy Owner B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $record = guardRecord($ownerA);

    $test = Livewire::test(OwnerGuardLazyStub::class, ['record' => $record]);

    preg_match('/__lazyLoad\(&#039;([^&]+)&#039;\)/', $test->html(), $matches);

    expect($matches[1] ?? null)->toBeString();

    $test->call('__lazyLoad', $matches[1]);

    // Mount hook ran on resume and stamped the visible record.
    expect($test->get('record'))->not->toBeNull()
        ->and($test->get('recordOwnerStamp'))->toBeString();

    // A fresh lazy mount resolving under another owner fails closed.
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $second = Livewire::test(OwnerGuardLazyStub::class, ['record' => guardRecord($ownerA)]);

    preg_match('/__lazyLoad\(&#039;([^&]+)&#039;\)/', $second->html(), $matches);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $second->call('__lazyLoad', $matches[1]);

    expect($second->get('record'))->toBeNull()
        ->and($second->get('recordOwnerStamp'))->toBeNull();
});

it('pins visibility to a configured fixed owner', function (): void {
    $ownerA = guardOwner('Pinned Owner A');
    $ownerB = guardOwner('Pinned Owner B');
    OwnerGuardPinnedFixture::$pinnedOwner = $ownerA;
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $record = OwnerContext::withOwner(
        $ownerA,
        fn () => OwnerGuardPinnedFixture::query()->create(['label' => 'pinned']),
    );

    // Ambient context differs from the record owner, but the pin governs.
    $component = app(OwnerGuardStubComponent::class);
    $component->record = $record;
    $component->mountVerifiesRecordOwnerContext();

    expect($component->record)->not->toBeNull();

    $component->hydrateVerifiesRecordOwnerContext();

    expect($component->record)->not->toBeNull();
});

it('keeps pinned records across ambient context switches', function (): void {
    $ownerA = guardOwner('Pin Switch A');
    $ownerB = guardOwner('Pin Switch B');
    $ownerC = guardOwner('Pin Switch C');
    OwnerGuardPinnedFixture::$pinnedOwner = $ownerA;
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $record = OwnerContext::withOwner(
        $ownerA,
        fn () => OwnerGuardPinnedFixture::query()->create(['label' => 'pinned-stable']),
    );

    $component = app(OwnerGuardStubComponent::class);
    $component->record = $record;
    $component->mountVerifiesRecordOwnerContext();

    OwnerContext::withOwner($ownerC, function () use ($component): void {
        $component->hydrateVerifiesRecordOwnerContext();
    });

    expect($component->record)->not->toBeNull();
});

it('treats a configured fixed owner as sufficient context', function (): void {
    $ownerA = guardOwner('Pin Sufficient A');
    OwnerGuardPinnedFixture::$pinnedOwner = $ownerA;
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver(null));

    $record = OwnerContext::withOwner(
        $ownerA,
        fn () => OwnerGuardPinnedFixture::query()->create(['label' => 'pinned-no-context']),
    );

    $component = app(OwnerGuardStubComponent::class);
    $component->record = $record;

    $component->mountVerifiesRecordOwnerContext();

    expect($component->record)->not->toBeNull();

    $component->hydrateVerifiesRecordOwnerContext();

    expect($component->record)->not->toBeNull();
});

it('fails closed for records outside the configured fixed owner', function (): void {
    $ownerA = guardOwner('Pin Exclude A');
    $ownerB = guardOwner('Pin Exclude B');
    OwnerGuardPinnedFixture::$pinnedOwner = $ownerA;
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $record = OwnerContext::withOwner(
        $ownerB,
        fn () => OwnerGuardPinnedFixture::query()->create(['label' => 'off-pin']),
    );

    $component = app(OwnerGuardStubComponent::class);
    $component->record = $record;
    $component->mountVerifiesRecordOwnerContext();

    expect($component->record)->toBeNull()
        ->and($component->recordOwnerStamp)->toBeNull();
});

it('clears a custom event prop through a real Livewire request cycle', function (): void {
    $ownerA = guardOwner('Event Cycle A');
    $ownerB = guardOwner('Event Cycle B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $test = Livewire::test(OwnerGuardEventStubComponent::class, ['event' => guardRecord($ownerA)]);

    expect($test->get('recordOwnerStamp'))->toBeString()
        ->and($test->get('event'))->not->toBeNull();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $test->call('$refresh');

    expect($test->get('event'))->toBeNull()
        ->and($test->get('recordOwnerStamp'))->toBeNull();
});

it('exposes Livewire-conventional hook names and a locked stamp', function (): void {
    $component = app(OwnerGuardStubComponent::class);

    expect(method_exists($component, 'mountVerifiesRecordOwnerContext'))->toBeTrue()
        ->and(method_exists($component, 'hydrateVerifiesRecordOwnerContext'))->toBeTrue();

    $stamp = new ReflectionProperty($component, 'recordOwnerStamp');

    expect($stamp->getAttributes(Locked::class))->not->toBeEmpty();
});

final class OwnerGuardStubComponent extends Component
{
    use VerifiesRecordOwnerContext;

    public ?Model $record = null;

    public function mount(?Model $record = null): void
    {
        $this->record = $record;
    }
}

final class OwnerGuardNoRecordStubComponent extends Component
{
    use VerifiesRecordOwnerContext;
}

final class OwnerGuardEventStubComponent extends Component
{
    use VerifiesRecordOwnerContext;

    public ?Model $event = null;

    public function mount(?Model $event = null): void
    {
        $this->event = $event;
    }

    public function ping(): void {}

    public function render(): string
    {
        return '<div>event stub</div>';
    }

    protected function ownerGuardedPropName(): string
    {
        return 'event';
    }
}

class OwnerGuardRoundTripStub extends Component
{
    use VerifiesRecordOwnerContext;

    public ?Model $record = null;

    public function mount(?Model $record = null): void
    {
        $this->record = $record;
    }

    public function ping(): void {}

    public function render(): string
    {
        return '<div>round-trip stub</div>';
    }
}

#[Lazy]
class OwnerGuardLazyStub extends OwnerGuardRoundTripStub {}

final class OwnerGuardRecordFixture extends Model
{
    use HasOwner;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_guard_records';
    }

    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return new OwnerScopeConfig(enabled: true, includeGlobal: false);
    }
}

final class OwnerGuardGlobalFixture extends Model
{
    use HasOwner;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_guard_records';
    }

    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return new OwnerScopeConfig(enabled: true, includeGlobal: true);
    }
}

final class OwnerGuardDisabledFixture extends Model
{
    use HasOwner;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_guard_records';
    }

    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return new OwnerScopeConfig(enabled: false);
    }
}

final class OwnerGuardPinnedFixture extends Model
{
    use HasOwner;

    public static ?Model $pinnedOwner = null;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_guard_records';
    }

    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return new OwnerScopeConfig(enabled: true, includeGlobal: false, owner: self::$pinnedOwner);
    }
}

final class OwnerGuardTenantFixture extends Model
{
    use HasOwner;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_guard_tenants';
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

final class OwnerGuardPlainFixture extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_guard_plains';
    }
}

final class OwnerGuardScopedFixture extends Model
{
    use HasOwner;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_guard_records';
    }

    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return new OwnerScopeConfig(enabled: true, includeGlobal: false);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('not-archived', function ($query): void {
            $query->where('label', '!=', 'archived');
        });
    }
}
