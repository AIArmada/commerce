<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerScopeConfigurable;
use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('batch_runner_fixtures');
    Schema::create('batch_runner_fixtures', function (Blueprint $table): void {
        $table->id();
        $table->nullableMorphs('owner');
        $table->string('label');
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('batch_runner_fixtures');
});

// ---------------------------------------------------------------------------
// Fixture model
// ---------------------------------------------------------------------------

final class BatchRunnerFixture extends Model implements OwnerScopeConfigurable
{
    use HasOwner;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'batch_runner_fixtures';
    }

    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return new OwnerScopeConfig(enabled: true, includeGlobal: false);
    }
}

// ---------------------------------------------------------------------------
// Owner discovery
// ---------------------------------------------------------------------------

it('discovers owners and runs callback per owner', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'batch-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'batch-b@example.com',
        'password' => 'secret',
    ]);

    DB::table('batch_runner_fixtures')->insert([
        ['owner_type' => $ownerA->getMorphClass(), 'owner_id' => $ownerA->getKey(), 'label' => 'a-1'],
        ['owner_type' => $ownerA->getMorphClass(), 'owner_id' => $ownerA->getKey(), 'label' => 'a-2'],
        ['owner_type' => $ownerB->getMorphClass(), 'owner_id' => $ownerB->getKey(), 'label' => 'b-1'],
    ]);

    OwnerContext::setForRequest(null);

    $runner = new OwnerBatchRunner(BatchRunnerFixture::class, [
        'enabled' => 'batch_runner_test.enabled',
    ]);

    config()->set('batch_runner_test.enabled', true);

    $executedFor = collect();

    $runner->forEach(function () use ($executedFor): int {
        $owner = OwnerContext::resolve();
        $executedFor->push($owner !== null ? $owner->getKey() : 'global');

        return 0;
    });

    expect($executedFor)->toHaveCount(2)
        ->and($executedFor)->toContain($ownerA->getKey())
        ->and($executedFor)->toContain($ownerB->getKey());
});

it('runs once when an owner is already resolved', function (): void {
    $owner = User::query()->create([
        'name' => 'Resolved Owner',
        'email' => 'resolved@example.com',
        'password' => 'secret',
    ]);

    DB::table('batch_runner_fixtures')->insert([
        ['owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'label' => 'owned'],
    ]);

    $callCount = 0;

    $runner = new OwnerBatchRunner(BatchRunnerFixture::class, [
        'enabled' => 'batch_runner_test.enabled',
    ]);

    config()->set('batch_runner_test.enabled', true);

    $result = OwnerContext::withOwner($owner, function () use ($runner, &$callCount): int {
        return $runner->run(function () use (&$callCount): int {
            $callCount++;

            return 42;
        });
    });

    expect($callCount)->toBe(1)
        ->and($result)->toBe(42);
});

it('runs in explicit global when no owner tuples exist', function (): void {
    OwnerContext::setForRequest(null);

    $runner = new OwnerBatchRunner(BatchRunnerFixture::class, [
        'enabled' => 'batch_runner_test.enabled',
    ]);

    config()->set('batch_runner_test.enabled', true);

    $result = $runner->run(function (): string {
        expect(OwnerContext::isExplicitGlobal())->toBeTrue();

        return 'global-mode';
    });

    expect($result)->toBe('global-mode');
});

it('skips owner iteration when owner is disabled', function (): void {
    $owner = User::query()->create([
        'name' => 'Disabled Owner',
        'email' => 'disabled@example.com',
        'password' => 'secret',
    ]);

    DB::table('batch_runner_fixtures')->insert([
        ['owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'label' => 'disabled'],
    ]);

    $runner = new OwnerBatchRunner(BatchRunnerFixture::class, [
        'enabled' => 'batch_runner_test.enabled',
    ]);

    config()->set('batch_runner_test.enabled', false);

    $callCount = 0;

    $runner->run(function () use (&$callCount): void {
        $callCount++;
    });

    expect($callCount)->toBe(1);
});

// ---------------------------------------------------------------------------
// forEach() collection
// ---------------------------------------------------------------------------

it('forEach collects per-owner results into a collection', function (): void {
    $ownerA = User::query()->create([
        'name' => 'ForEach A',
        'email' => 'foreach-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'ForEach B',
        'email' => 'foreach-b@example.com',
        'password' => 'secret',
    ]);

    DB::table('batch_runner_fixtures')->insert([
        ['owner_type' => $ownerA->getMorphClass(), 'owner_id' => $ownerA->getKey(), 'label' => 'a'],
        ['owner_type' => $ownerB->getMorphClass(), 'owner_id' => $ownerB->getKey(), 'label' => 'b'],
    ]);

    OwnerContext::setForRequest(null);

    $runner = new OwnerBatchRunner(BatchRunnerFixture::class, [
        'enabled' => 'batch_runner_test.enabled',
    ]);

    config()->set('batch_runner_test.enabled', true);

    $results = $runner->forEach(fn (): string => 'result-' . (OwnerContext::resolve()?->getKey() ?? 'global'));

    expect($results)->toHaveCount(2);
});

// ---------------------------------------------------------------------------
// Reduction
// ---------------------------------------------------------------------------

it('reduces integer results into a sum via run()', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Reduce A',
        'email' => 'reduce-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Reduce B',
        'email' => 'reduce-b@example.com',
        'password' => 'secret',
    ]);

    DB::table('batch_runner_fixtures')->insert([
        ['owner_type' => $ownerA->getMorphClass(), 'owner_id' => $ownerA->getKey(), 'label' => 'a'],
        ['owner_type' => $ownerB->getMorphClass(), 'owner_id' => $ownerB->getKey(), 'label' => 'b'],
    ]);

    OwnerContext::setForRequest(null);

    $runner = new OwnerBatchRunner(BatchRunnerFixture::class, [
        'enabled' => 'batch_runner_test.enabled',
    ]);

    config()->set('batch_runner_test.enabled', true);

    $result = $runner->run(fn (): int => 10);

    expect($result)->toBe(20);
});

it('reduces array results into a merged sum via run()', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Array A',
        'email' => 'array-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Array B',
        'email' => 'array-b@example.com',
        'password' => 'secret',
    ]);

    DB::table('batch_runner_fixtures')->insert([
        ['owner_type' => $ownerA->getMorphClass(), 'owner_id' => $ownerA->getKey(), 'label' => 'a'],
        ['owner_type' => $ownerB->getMorphClass(), 'owner_id' => $ownerB->getKey(), 'label' => 'b'],
    ]);

    OwnerContext::setForRequest(null);

    $runner = new OwnerBatchRunner(BatchRunnerFixture::class, [
        'enabled' => 'batch_runner_test.enabled',
    ]);

    config()->set('batch_runner_test.enabled', true);

    $result = $runner->run(fn (): array => [
        'processed' => 5,
        'skipped' => 2,
        'errors' => 0,
    ]);

    expect($result)->toBe([
        'processed' => 10,
        'skipped' => 4,
        'errors' => 0,
    ]);
});

// ---------------------------------------------------------------------------
// Explicit global handling
// ---------------------------------------------------------------------------

it('runs in explicit global when callback is invoked with null owner', function (): void {
    OwnerContext::setForRequest(null);

    $runner = new OwnerBatchRunner(BatchRunnerFixture::class, [
        'enabled' => 'batch_runner_test.enabled',
    ]);

    config()->set('batch_runner_test.enabled', true);

    $foundCount = $runner->run(function (): int {
        $query = DB::table('batch_runner_fixtures');
        $owner = OwnerContext::resolve();

        OwnerQuery::applyToQueryBuilder($query, $owner);

        return $query->count();
    });

    expect($foundCount)->toBe(0);
});

it('deduplicates explicit global tuples when multiple global rows exist', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Dedup A',
        'email' => 'dedup-a@example.com',
        'password' => 'secret',
    ]);

    DB::table('batch_runner_fixtures')->insert([
        ['owner_type' => null, 'owner_id' => null, 'label' => 'global-1'],
        ['owner_type' => null, 'owner_id' => null, 'label' => 'global-2'],
        ['owner_type' => $ownerA->getMorphClass(), 'owner_id' => $ownerA->getKey(), 'label' => 'owner'],
    ]);

    OwnerContext::setForRequest(null);

    $callCount = 0;
    $runner = new OwnerBatchRunner(BatchRunnerFixture::class, [
        'enabled' => 'batch_runner_test.enabled',
    ]);

    config()->set('batch_runner_test.enabled', true);

    $runner->forEach(function () use (&$callCount): int {
        $callCount++;

        return 0;
    });

    expect($callCount)->toBe(2);
});

// ---------------------------------------------------------------------------
// include_global toggling
// ---------------------------------------------------------------------------

it('temporarily disables include_global during iteration', function (): void {
    config()->set('batch_runner_include_test.enabled', true);
    config()->set('batch_runner_include_test.include_global', true);

    $owner = User::query()->create([
        'name' => 'Include Global',
        'email' => 'include-global@example.com',
        'password' => 'secret',
    ]);

    DB::table('batch_runner_fixtures')->insert([
        ['owner_type' => null, 'owner_id' => null, 'label' => 'global'],
        ['owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'label' => 'owner'],
    ]);

    OwnerContext::setForRequest(null);

    $runner = new OwnerBatchRunner(BatchRunnerFixture::class, [
        'enabled' => 'batch_runner_include_test.enabled',
        'include_global' => 'batch_runner_include_test.include_global',
    ]);

    $includeGlobalValues = collect();

    $runner->forEach(function () use ($includeGlobalValues): int {
        $includeGlobalValues->push(config('batch_runner_include_test.include_global'));

        return 0;
    });

    expect($includeGlobalValues->every(fn ($v): bool => $v === false))->toBeTrue();
    expect(config('batch_runner_include_test.include_global'))->toBe(true);
});
