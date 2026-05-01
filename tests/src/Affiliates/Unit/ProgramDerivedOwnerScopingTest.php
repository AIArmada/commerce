<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
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

            public function resolve(): ?Model
            {
                return $this->owner;
            }
        });
    };

    $setOwner($ownerA);

    $programA = AffiliateProgram::create([
        'name' => 'Program A',
        'slug' => 'program-a',
        'status' => ProgramStatus::Active,
        'requires_approval' => false,
        'is_public' => true,
        'default_commission_rate_basis_points' => 1000,
        'commission_type' => CommissionType::Percentage,
        'cookie_lifetime_days' => 30,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $setOwner($ownerB);

    $programB = AffiliateProgram::create([
        'name' => 'Program B',
        'slug' => 'program-b',
        'status' => ProgramStatus::Active,
        'requires_approval' => false,
        'is_public' => true,
        'default_commission_rate_basis_points' => 1000,
        'commission_type' => CommissionType::Percentage,
        'cookie_lifetime_days' => 30,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

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
        'asset_url' => 'https://example.com/a.jpg',
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
        'asset_url' => 'https://example.com/b.jpg',
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

class ProgramDerivedOwnerScopingTestOwner extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'test_products';

    protected $guarded = [];

    protected $keyType = 'string';
}
