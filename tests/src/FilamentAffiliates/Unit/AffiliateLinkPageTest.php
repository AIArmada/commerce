<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\States\Active;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliates\Resources\AffiliateLinkResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateLinkResource\Pages\CreateAffiliateLink;
use AIArmada\CommerceSupport\Models\Permission;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);

    AffiliateLink::query()->delete();
    AffiliateProgram::query()->delete();
    Affiliate::query()->delete();
    User::query()->delete();
    Permission::query()->delete();
});

it('enforces affiliate.view permission for link view ability', function (): void {
    $user = User::create([
        'name' => 'Link Viewer',
        'email' => 'link-viewer@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $affiliate = Affiliate::create([
        'code' => 'AFF-' . str()->uuid(),
        'name' => 'Link View Affiliate',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $link = AffiliateLink::create([
        'affiliate_id' => $affiliate->getKey(),
        'destination_url' => 'https://example.com/offer',
        'tracking_url' => 'https://track.example.com/a/456',
        'short_url' => 'https://short.example.com/y',
        'campaign' => 'View Campaign',
        'is_active' => true,
    ]);

    expect(AffiliateLinkResource::canView($link))->toBeFalse();

    Permission::create(['name' => 'affiliate.view', 'guard_name' => 'web']);
    $user->givePermissionTo('affiliate.view');

    expect(AffiliateLinkResource::canView($link))->toBeTrue();
});

it('registers affiliate link view route in pages map', function (): void {
    expect(AffiliateLinkResource::getPages())
        ->toBeArray()
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('view')
        ->toHaveKey('edit');
});

it('maps link create form data to owned affiliate and program ids', function (): void {
    $owner = User::create([
        'name' => 'Link Owner',
        'email' => 'link-owner@example.com',
        'password' => 'secret',
    ]);

    $affiliate = OwnerContext::withOwner($owner, function (): Affiliate {
        return Affiliate::create([
            'code' => 'AFF-' . Str::uuid(),
            'name' => 'Link Form Affiliate',
            'status' => Active::class,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);
    });

    $program = OwnerContext::withOwner($owner, function (): AffiliateProgram {
        return AffiliateProgram::create([
            'name' => 'Link Form Program',
            'status' => ProgramStatus::Active,
            'is_public' => true,
            'requires_approval' => false,
            'default_commission_rate_basis_points' => 500,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
            'terms_url' => 'https://example.com/terms',
        ]);
    });

    $page = new CreateAffiliateLink;
    $method = new ReflectionMethod(CreateAffiliateLink::class, 'mutateFormDataBeforeCreate');
    $method->setAccessible(true);

    /** @var array<string, mixed> $mutated */
    $mutated = OwnerContext::withOwner($owner, function () use ($method, $page, $affiliate, $program): array {
        return $method->invoke($page, [
            'affiliate_id' => $affiliate->getKey(),
            'program_id' => $program->getKey(),
            'destination_url' => 'https://example.com/offer',
            'tracking_url' => 'https://track.example.com/a/123',
            'short_url' => 'https://short.example.com/x',
            'custom_slug' => 'spring-offer',
            'campaign' => 'Spring Campaign',
            'sub_id' => 'sub-a',
            'sub_id_2' => 'sub-b',
            'sub_id_3' => 'sub-c',
            'is_active' => true,
        ]);
    });

    expect($mutated['affiliate_id'])->toBe((string) $affiliate->getKey())
        ->and($mutated['program_id'])->toBe((string) $program->getKey());
});

it('rejects program ids outside the current owner scope during link create', function (): void {
    $ownerA = User::create([
        'name' => 'Link Program Owner A',
        'email' => 'link-program-owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::create([
        'name' => 'Link Program Owner B',
        'email' => 'link-program-owner-b@example.com',
        'password' => 'secret',
    ]);

    $affiliateA = OwnerContext::withOwner($ownerA, function (): Affiliate {
        return Affiliate::create([
            'code' => 'AFF-' . Str::uuid(),
            'name' => 'Owner A Affiliate',
            'status' => Active::class,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);
    });

    $programB = OwnerContext::withOwner($ownerB, function (): AffiliateProgram {
        return AffiliateProgram::create([
            'name' => 'Owner B Program',
            'status' => ProgramStatus::Active,
            'is_public' => true,
            'requires_approval' => false,
            'default_commission_rate_basis_points' => 600,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
            'terms_url' => 'https://example.com/terms',
        ]);
    });

    $page = new CreateAffiliateLink;
    $method = new ReflectionMethod(CreateAffiliateLink::class, 'mutateFormDataBeforeCreate');
    $method->setAccessible(true);

    OwnerContext::withOwner($ownerA, function () use ($method, $page, $affiliateA, $programB): void {
        expect(fn () => $method->invoke($page, [
            'affiliate_id' => $affiliateA->getKey(),
            'program_id' => $programB->getKey(),
            'destination_url' => 'https://example.com/offer',
            'tracking_url' => 'https://track.example.com/a/123',
        ]))->toThrow(ValidationException::class, 'The selected program is not accessible in the current owner scope.');
    });
});
