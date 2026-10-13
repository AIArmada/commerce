<?php

declare(strict_types=1);

use AIArmada\Rewards\Commands\ApplyRewardSourceCorrectionsCommand;
use AIArmada\Rewards\Commands\ExpireRewardPointLotsCommand;
use AIArmada\Rewards\Contracts\RewardBenefitCatalog;
use AIArmada\Rewards\Contracts\RewardBenefitCouponFulfillment;
use AIArmada\Rewards\Contracts\RewardCorrectionKeySource;
use AIArmada\Rewards\Contracts\RewardFinancialReadinessChecker;
use AIArmada\Rewards\Contracts\RewardGratitudePlanner;
use AIArmada\Rewards\Contracts\RewardPayoutExecutor;
use AIArmada\Rewards\Contracts\RewardPayoutMethodCatalog;
use AIArmada\Rewards\Contracts\RewardReadinessChecker;
use AIArmada\Rewards\Contracts\RewardRecipientResolver;
use AIArmada\Rewards\Contracts\RewardReservationResolver;
use AIArmada\Rewards\Contracts\RewardSourceFactReader;
use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Models\RewardPayoutRequest;
use AIArmada\Rewards\Models\RewardProgram;
use AIArmada\Rewards\RewardsServiceProvider;
use AIArmada\Rewards\Support\ManualRewardPayoutExecutor;
use AIArmada\Rewards\Support\ManualRewardPayoutMethodCatalog;
use AIArmada\Rewards\Support\MorphRewardRecipientResolver;
use AIArmada\Rewards\Support\NullRewardBenefitCouponFulfillment;
use AIArmada\Rewards\Support\NullRewardCorrectionKeySource;
use AIArmada\Rewards\Support\NullRewardFinancialReadinessChecker;
use AIArmada\Rewards\Support\NullRewardGratitudePlanner;
use AIArmada\Rewards\Support\NullRewardReadinessChecker;
use AIArmada\Rewards\Support\NullRewardReservationResolver;
use AIArmada\Rewards\Support\NullRewardSourceFactReader;
use AIArmada\Rewards\Support\RewardBenefitCatalogService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

test('service provider registers', function (): void {
    $providers = app()->getLoadedProviders();

    expect(isset($providers[RewardsServiceProvider::class]))->toBeTrue();
});

test('config publishes and reads correctly', function (): void {
    expect(config('rewards.database.tables.programs'))->toBe('reward_programs');
    expect(config('rewards.database.tables.payout_requests'))->toBe('reward_payout_requests');
    expect(config('rewards.owner.enabled'))->toBeTrue();
    expect(config('rewards.currencies'))->toContain('MYR');
    expect(config('rewards.limits.max_points'))->toBe(1000000);
    expect(config('rewards.payouts.methods'))->toBe(['manual']);
});

test('all thirty tables exist after migration', function (): void {
    $tables = config('rewards.database.tables');

    expect($tables)->toHaveCount(30);

    foreach ($tables as $key => $table) {
        expect(Schema::hasTable($table))->toBeTrue("Expected {$table} ({$key}) to exist");
    }
});

test('program table carries the uuid owner morph', function (): void {
    expect(Schema::hasColumns('reward_programs', ['owner_type', 'owner_id']))->toBeTrue();

    $column = collect(Schema::getColumns('reward_programs'))->firstWhere('name', 'owner_id');

    expect($column)->not->toBeNull()
        ->and((string) $column['type_name'])->toContain('varchar');
});

test('payout requests use generic external refs, not affiliate columns', function (): void {
    expect(Schema::hasColumns('reward_payout_requests', [
        'external_payout_id', 'external_reference_id', 'external_provider',
    ]))->toBeTrue()
        ->and(Schema::hasColumn('reward_payout_requests', 'affiliate_payout_id'))->toBeFalse()
        ->and(Schema::hasColumn('reward_payout_requests', 'backing_conversion_id'))->toBeFalse();
});

test('models resolve tables from config with uuid keys', function (): void {
    expect((new RewardProgram)->getTable())->toBe('reward_programs')
        ->and((new RewardAward)->getTable())->toBe('reward_awards')
        ->and((new RewardPayoutRequest)->getTable())->toBe('reward_payout_requests')
        ->and((new RewardProgram)->getKeyType())->toBe('string')
        ->and((new RewardProgram)->getIncrementing())->toBeFalse();
});

test('contracts resolve to fail-closed defaults', function (): void {
    expect(app(RewardSourceFactReader::class))->toBeInstanceOf(NullRewardSourceFactReader::class)
        ->and(app(RewardReservationResolver::class))->toBeInstanceOf(NullRewardReservationResolver::class)
        ->and(app(RewardReadinessChecker::class))->toBeInstanceOf(NullRewardReadinessChecker::class)
        ->and(app(RewardFinancialReadinessChecker::class))->toBeInstanceOf(NullRewardFinancialReadinessChecker::class)
        ->and(app(RewardBenefitCatalog::class))->toBeInstanceOf(RewardBenefitCatalogService::class)
        ->and(app(RewardPayoutExecutor::class))->toBeInstanceOf(ManualRewardPayoutExecutor::class)
        ->and(app(RewardPayoutMethodCatalog::class))->toBeInstanceOf(ManualRewardPayoutMethodCatalog::class)
        ->and(app(RewardBenefitCouponFulfillment::class))->toBeInstanceOf(NullRewardBenefitCouponFulfillment::class)
        ->and(app(RewardRecipientResolver::class))->toBeInstanceOf(MorphRewardRecipientResolver::class)
        ->and(app(RewardCorrectionKeySource::class))->toBeInstanceOf(NullRewardCorrectionKeySource::class)
        ->and(app(RewardGratitudePlanner::class))->toBeInstanceOf(NullRewardGratitudePlanner::class);
});

test('artisan commands are registered', function (): void {
    expect(array_keys(Artisan::all()))->toContain(
        'rewards:apply-source-corrections',
        'rewards:expire-point-lots',
    )
        ->and(app(ApplyRewardSourceCorrectionsCommand::class))->toBeInstanceOf(ApplyRewardSourceCorrectionsCommand::class)
        ->and(app(ExpireRewardPointLotsCommand::class))->toBeInstanceOf(ExpireRewardPointLotsCommand::class);
});
