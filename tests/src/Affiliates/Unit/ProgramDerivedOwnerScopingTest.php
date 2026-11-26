<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateVolumeTier;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramTier;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('scopes program tiers and creatives by current owner', function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);
    config()->set('affiliates.owner.auto_assign_on_create', false);

    $ownerA = ProgramDerivedOwnerScopingTestOwner::create(['name' => 'Owner A']);
    $ownerB = ProgramDerivedOwnerScopingTestOwner::create(['name' => 'Owner B']);

    $setOwner = function (?Model $owner): void {
        app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
        {
            public function __construct(
                private readonly ?Model $owner,
            ) {}

            public function resolve(): ?RecordReference
            {
                return EloquentRecordReference::fromModel($this->owner);
            }
        });
    };

    $setOwner($ownerA);

    $programA = new AffiliateProgram([
        'name' => 'Program A',
        'slug' => 'program-a',
        'status' => ProgramStatus::Active,
        'requires_approval' => false,
        'is_public' => true,
        'default_commission_rate_basis_points' => 1000,
        'commission_type' => CommissionType::Percentage,
        'cookie_lifetime_days' => 30,
    ]);
    $programA->forceFill([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);
    $programA->save();

    $setOwner($ownerB);

    $programB = new AffiliateProgram([
        'name' => 'Program B',
        'slug' => 'program-b',
        'status' => ProgramStatus::Active,
        'requires_approval' => false,
        'is_public' => true,
        'default_commission_rate_basis_points' => 1000,
        'commission_type' => CommissionType::Percentage,
        'cookie_lifetime_days' => 30,
    ]);
    $programB->forceFill([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);
    $programB->save();

    $setOwner($ownerA);

    AffiliateProgramTier::create([
        'program_id' => $programA->getKey(),
        'name' => 'Tier A',
        'level' => 1,
        'commission_rate_basis_points' => 1000,
        'min_conversions' => 0,
        'min_revenue' => 0,
    ]);

    AffiliateProgramCreative::create([
        'program_id' => $programA->getKey(),
        'type' => 'banner',
        'name' => 'Creative A',
        'destination_url' => 'https://example.com/a',
        'tracking_code' => 'TRACK-A',
    ]);

    $setOwner($ownerB);

    AffiliateProgramTier::create([
        'program_id' => $programB->getKey(),
        'name' => 'Tier B',
        'level' => 1,
        'commission_rate_basis_points' => 1200,
        'min_conversions' => 0,
        'min_revenue' => 0,
    ]);

    AffiliateProgramCreative::create([
        'program_id' => $programB->getKey(),
        'type' => 'banner',
        'name' => 'Creative B',
        'destination_url' => 'https://example.com/b',
        'tracking_code' => 'TRACK-B',
    ]);

    $setOwner($ownerA);

    expect(AffiliateProgramTier::query()->count())->toBe(1)
        ->and(AffiliateProgramCreative::query()->count())->toBe(1)
        ->and(AffiliateProgramTier::query()->first()?->program_id)->toBe($programA->getKey())
        ->and(AffiliateProgramCreative::query()->first()?->program_id)->toBe($programA->getKey());

    $setOwner($ownerB);

    expect(AffiliateProgramTier::query()->count())->toBe(1)
        ->and(AffiliateProgramCreative::query()->count())->toBe(1)
        ->and(AffiliateProgramTier::query()->first()?->program_id)->toBe($programB->getKey())
        ->and(AffiliateProgramCreative::query()->first()?->program_id)->toBe($programB->getKey());
});


it('permits global program-derived reads without permitting tenant writes or reparenting', function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', true);
    $owner = ProgramDerivedOwnerScopingTestOwner::create(['name' => 'Tenant']);

    $globalProgram = OwnerContext::withOwner(null, static fn () => AffiliateProgram::create([
        'name' => 'Global program',
        'status' => ProgramStatus::Active,
        'commission_type' => CommissionType::Percentage,
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]));

    $globalTier = OwnerContext::withOwner(null, static fn () => AffiliateVolumeTier::create([
        'program_id' => $globalProgram->id,
        'name' => 'Global volume tier',
        'min_volume_minor' => 0,
        'commission_rate_basis_points' => 1000,
        'currency' => 'USD',
        'period' => 'monthly',
    ]));

    OwnerContext::withOwner($owner, function () use ($globalProgram, $globalTier): void {
        $localProgram = AffiliateProgram::create([
            'name' => 'Local program',
            'status' => ProgramStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'default_commission_rate_basis_points' => 1000,
            'cookie_lifetime_days' => 30,
        ]);

        expect(AffiliateVolumeTier::query()->find($globalTier->id))->not->toBeNull()
            ->and(fn () => AffiliateVolumeTier::query()->findOrFail($globalTier->id)->delete())->toThrow(AuthorizationException::class)
            ->and(fn () => AffiliateVolumeTier::query()->findOrFail($globalTier->id)->update(['program_id' => $localProgram->id]))->toThrow(AuthorizationException::class)
            ->and(fn () => AffiliateVolumeTier::create([
                'program_id' => $globalProgram->id,
                'name' => 'Forbidden tier',
                'min_volume_minor' => 0,
                'commission_rate_basis_points' => 1000,
                'currency' => 'USD',
                'period' => 'monthly',
            ]))->toThrow(AuthorizationException::class);
    });

    OwnerContext::withOwner(null, function () use ($globalTier, $globalProgram): void {
        expect(AffiliateVolumeTier::query()->findOrFail($globalTier->id)->program_id)->toBe($globalProgram->id);
    });
});

class ProgramDerivedOwnerScopingTestOwner extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'test_products';

    protected $guarded = [];

    protected $keyType = 'string';
}
