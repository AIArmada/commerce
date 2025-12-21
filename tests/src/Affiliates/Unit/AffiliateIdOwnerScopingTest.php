<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Models\AffiliateSupportTicket;
use AIArmada\Affiliates\Models\AffiliateTaxDocument;
use AIArmada\Affiliates\Models\AffiliateTrainingModule;
use AIArmada\Affiliates\Models\AffiliateTrainingProgress;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

it('scopes affiliate-derived rows by affiliate_id through the affiliate owner scope', function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);
    config()->set('affiliates.owner.auto_assign_on_create', false);

    $ownerA = AffiliateIdOwnerScopingTestOwner::create(['name' => 'Owner A']);
    $ownerB = AffiliateIdOwnerScopingTestOwner::create(['name' => 'Owner B']);

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

    $affiliateA = Affiliate::create([
        'code' => 'AFF-REL-A',
        'name' => 'Affiliate A',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $affiliateB = Affiliate::create([
        'code' => 'AFF-REL-B',
        'name' => 'Affiliate B',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $program = AffiliateProgram::create([
        'name' => 'Global Program',
        'slug' => 'global-program',
        'status' => 'active',
        'is_public' => true,
        'requires_approval' => false,
        'default_commission_rate_basis_points' => 1000,
        'commission_type' => 'percentage',
        'cookie_lifetime_days' => 30,
    ]);

    $setOwner($ownerA);

    AffiliateProgramMembership::create([
        'affiliate_id' => $affiliateA->getKey(),
        'program_id' => $program->getKey(),
        'status' => 'approved',
        'applied_at' => now(),
        'approved_at' => now(),
    ]);

    $setOwner($ownerB);

    AffiliateProgramMembership::create([
        'affiliate_id' => $affiliateB->getKey(),
        'program_id' => $program->getKey(),
        'status' => 'approved',
        'applied_at' => now(),
        'approved_at' => now(),
    ]);

    $setOwner($ownerA);

    AffiliateLink::create([
        'affiliate_id' => $affiliateA->getKey(),
        'program_id' => $program->getKey(),
        'destination_url' => 'https://example.com/a',
        'tracking_url' => 'https://example.com/t/a',
        'is_active' => true,
    ]);

    $setOwner($ownerB);

    AffiliateLink::create([
        'affiliate_id' => $affiliateB->getKey(),
        'program_id' => $program->getKey(),
        'destination_url' => 'https://example.com/b',
        'tracking_url' => 'https://example.com/t/b',
        'is_active' => true,
    ]);

    $setOwner($ownerA);

    AffiliateSupportTicket::create([
        'affiliate_id' => $affiliateA->getKey(),
        'subject' => 'A ticket',
        'category' => 'general',
        'priority' => 'normal',
        'status' => 'open',
    ]);

    $setOwner($ownerB);

    AffiliateSupportTicket::create([
        'affiliate_id' => $affiliateB->getKey(),
        'subject' => 'B ticket',
        'category' => 'general',
        'priority' => 'normal',
        'status' => 'open',
    ]);

    $setOwner($ownerA);

    AffiliateTaxDocument::create([
        'affiliate_id' => $affiliateA->getKey(),
        'document_type' => '1099-NEC',
        'tax_year' => 2025,
        'status' => 'pending',
        'total_amount_minor' => 60000,
        'currency' => 'USD',
    ]);

    $setOwner($ownerB);

    AffiliateTaxDocument::create([
        'affiliate_id' => $affiliateB->getKey(),
        'document_type' => '1099-NEC',
        'tax_year' => 2025,
        'status' => 'pending',
        'total_amount_minor' => 60000,
        'currency' => 'USD',
    ]);

    $module = AffiliateTrainingModule::create([
        'title' => 'Intro',
        'type' => 'article',
        'duration_minutes' => 5,
        'sort_order' => 1,
        'is_required' => false,
        'is_active' => true,
    ]);

    $setOwner($ownerA);

    AffiliateTrainingProgress::create([
        'affiliate_id' => $affiliateA->getKey(),
        'module_id' => $module->getKey(),
        'progress_percent' => 50,
    ]);

    $setOwner($ownerB);

    AffiliateTrainingProgress::create([
        'affiliate_id' => $affiliateB->getKey(),
        'module_id' => $module->getKey(),
        'progress_percent' => 50,
    ]);

    $setOwner($ownerA);

    expect(AffiliateLink::query()->count())->toBe(1)
        ->and(AffiliateSupportTicket::query()->count())->toBe(1)
        ->and(AffiliateTaxDocument::query()->count())->toBe(1)
        ->and(AffiliateTrainingProgress::query()->count())->toBe(1)
        ->and(AffiliateProgramMembership::query()->count())->toBe(1);

    $setOwner($ownerB);

    expect(AffiliateLink::query()->count())->toBe(1)
        ->and(AffiliateSupportTicket::query()->count())->toBe(1)
        ->and(AffiliateTaxDocument::query()->count())->toBe(1)
        ->and(AffiliateTrainingProgress::query()->count())->toBe(1)
        ->and(AffiliateProgramMembership::query()->count())->toBe(1);
});

class AffiliateIdOwnerScopingTestOwner extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'test_products';

    protected $guarded = [];

    protected $keyType = 'string';
}
