<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Affiliates\CreateTrackingLink;
use AIArmada\Affiliates\Actions\Conversions\RecordAffiliateOutcome;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->affiliate = Affiliate::create([
        'code' => 'LINK-AFFILIATE',
        'name' => 'Link Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 0,
        'currency' => 'MYR',
    ]);
});

test('it creates a permanent tracked link and preserves subject provenance', function (): void {
    config(['affiliates.links.allowed_hosts' => ['ilmu360.test']]);
    $link = CreateTrackingLink::run($this->affiliate, 'https://ilmu360.test/majlis/ramadan', [
        'subject_type' => 'event', 'subject_key' => 'event:ramadan',
    ]);
    expect($link->tracking_url)->toContain('/go/')
        ->and(parse_url($link->tracking_url, PHP_URL_QUERY))->toBeNull()
        ->and($link->subject_type)->toBe('event');
});

test('it records an attributed outcome without requiring a cart', function (): void {
    $attribution = AffiliateAttribution::create([
        'affiliate_id' => $this->affiliate->getKey(),
        'affiliate_code' => $this->affiliate->code,
        'subject_type' => 'event',
        'subject_key' => 'event:ramadan',
        'subject_id' => 'event-id',
        'subject_instance' => 'web',
    ]);

    $conversion = app(RecordAffiliateOutcome::class)->handle(
        attribution: $attribution,
        conversionType: 'event_checkin',
        externalReference: 'event-checkin:1',
        payload: [
            'status' => ApprovedConversion::class,
            'subject_id' => 'event-id',
            'dispatch_event' => false,
        ],
    );

    expect($conversion)
        ->not()->toBeNull()
        ->and($conversion?->affiliateCode)->toBe($this->affiliate->code)
        ->and($conversion?->conversionType)->toBe('event_checkin');
});

test('it creates a program tracking link when owner scoping is disabled without an owner', function (): void {
    $previousResolver = app(OwnerResolverInterface::class);
    $previousOwnerEnabled = config('affiliates.owner.enabled');
    $previousAllowedHosts = config('affiliates.links.allowed_hosts');

    config(['affiliates.owner.enabled' => false, 'affiliates.links.allowed_hosts' => ['ilmu360.test']]);
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver(null));

    try {
        $program = AffiliateProgram::create([
            'name' => 'Tracking Link Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'visibility' => ProgramVisibility::Public,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        $link = CreateTrackingLink::run($this->affiliate, 'https://ilmu360.test/majlis/ramadan', [
            'program_id' => $program->getKey(),
        ]);

        expect($link->program_id)->toBe((string) $program->getKey())
            ->and($link->tracking_url)->toContain('/go/')
            ->and(AffiliateLink::query()->whereKey($link->getKey())->exists())->toBeTrue()
            ->and($link->trackedLink()->exists())->toBeTrue()
            ->and($link->program?->getKey())->toBe((string) $program->getKey());
    } finally {
        config(['affiliates.owner.enabled' => $previousOwnerEnabled, 'affiliates.links.allowed_hosts' => $previousAllowedHosts]);
        app()->instance(OwnerResolverInterface::class, $previousResolver);
    }
});

test('it rejects an unknown program when owner scoping is disabled without an owner', function (): void {
    $previousResolver = app(OwnerResolverInterface::class);
    $previousOwnerEnabled = config('affiliates.owner.enabled');
    $previousAllowedHosts = config('affiliates.links.allowed_hosts');

    config(['affiliates.owner.enabled' => false, 'affiliates.links.allowed_hosts' => ['ilmu360.test']]);
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver(null));

    try {
        expect(fn () => CreateTrackingLink::run($this->affiliate, 'https://ilmu360.test/majlis/ramadan', [
            'program_id' => (string) Str::uuid(),
        ]))->toThrow(ModelNotFoundException::class);
    } finally {
        config(['affiliates.owner.enabled' => $previousOwnerEnabled, 'affiliates.links.allowed_hosts' => $previousAllowedHosts]);
        app()->instance(OwnerResolverInterface::class, $previousResolver);
    }
});

test('it rejects a cross-tenant program when owner scoping is enabled', function (): void {
    $previousOwnerEnabled = config('affiliates.owner.enabled');
    $previousIncludeGlobal = config('affiliates.owner.include_global');
    $previousAutoAssign = config('affiliates.owner.auto_assign_on_create');
    $previousAllowedHosts = config('affiliates.links.allowed_hosts');

    config([
        'affiliates.owner.enabled' => true,
        'affiliates.owner.include_global' => false,
        'affiliates.owner.auto_assign_on_create' => true,
        'affiliates.links.allowed_hosts' => ['merchant.example'],
    ]);

    try {
        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'tracking-link-owner-a@example.com',
            'password' => 'secret',
        ]);

        $ownerB = User::query()->create([
            'name' => 'Owner B',
            'email' => 'tracking-link-owner-b@example.com',
            'password' => 'secret',
        ]);

        $affiliateA = OwnerContext::withOwner($ownerA, fn () => Affiliate::create([
            'code' => 'LINK-OWNER-A',
            'name' => 'Owner A Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 0,
            'currency' => 'MYR',
        ]));

        $programB = OwnerContext::withOwner($ownerB, fn () => AffiliateProgram::create([
            'name' => 'Owner B Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'visibility' => ProgramVisibility::Public,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]));

        OwnerContext::withOwner($ownerA, function () use ($affiliateA, $programB): void {
            expect(fn () => CreateTrackingLink::run($affiliateA, 'https://merchant.example/summer', [
                'program_id' => $programB->getKey(),
            ]))->toThrow(AuthorizationException::class, 'Selected program is not accessible in the current owner scope.');
        });
    } finally {
        config([
            'affiliates.owner.enabled' => $previousOwnerEnabled,
            'affiliates.owner.include_global' => $previousIncludeGlobal,
            'affiliates.owner.auto_assign_on_create' => $previousAutoAssign,
            'affiliates.links.allowed_hosts' => $previousAllowedHosts,
        ]);
    }
});
