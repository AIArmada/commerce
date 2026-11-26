<?php

declare(strict_types=1);

use AIArmada\Affiliates\Data\AffiliateOutcomeDisposition;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Features\Attribution\Actions\CreateTrackingLink;
use AIArmada\Affiliates\Features\Attribution\Api\TrackingLinkAdministration;
use AIArmada\Affiliates\Features\Attribution\EntryPoints\Listeners\IncrementAffiliateLinkClicks;
use AIArmada\Affiliates\Features\Attribution\Extensions\AffiliateLinkGate;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateAttribution;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateLink;
use AIArmada\Affiliates\Features\Conversions\Api\OutcomePosting;
use AIArmada\Affiliates\Features\Conversions\States\ApprovedConversion;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Links\Events\LinkClicked;
use AIArmada\Links\Models\LinkClick;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
    $link = app(CreateTrackingLink::class)->handle((string) $this->affiliate->getKey(), 'https://ilmu360.test/majlis/ramadan', [
        'subject_type' => 'event', 'subject_key' => 'event:ramadan',
    ]);
    expect($link->tracking_url)->toContain('/go/')
        ->and(parse_url($link->tracking_url, PHP_URL_QUERY))->toBeNull()
        ->and($link->subject_type)->toBe('event');
});

test('it stores a caller-supplied opaque public token atomically', function (): void {
    config(['affiliates.links.allowed_hosts' => ['ilmu360.test']]);
    $link = app(CreateTrackingLink::class)->handle((string) $this->affiliate->getKey(), 'https://ilmu360.test/majlis/ramadan', [
        'custom_slug' => 'abc123xyz7890001',
    ]);
    expect($link->custom_slug)->toBe('abc123xyz7890001')
        ->and($link->tracking_url)->toContain('/go/')
        ->and($link->trackedLink()->exists())->toBeTrue()
        ->and(AffiliateLink::query()->whereKey($link->getKey())->value('custom_slug'))->toBe('abc123xyz7890001');
});

test('it rejects a malformed caller-supplied public token', function (): void {
    config(['affiliates.links.allowed_hosts' => ['ilmu360.test']]);
    expect(fn () => app(CreateTrackingLink::class)->handle((string) $this->affiliate->getKey(), 'https://ilmu360.test/majlis/ramadan', [
        'custom_slug' => 'NOT-A-TOKEN!',
    ]))->toThrow(ValidationException::class);
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

    $result = app(OutcomePosting::class)->post(
        attributionId: (string) $attribution->getKey(),
        conversionType: 'event_checkin',
        businessKey: 'tracking-link-test:event_checkin:event-checkin-1',
        payload: [
            'status' => ApprovedConversion::class,
            'subject_id' => 'event-id',
            'dispatch_event' => false,
        ],
    );

    expect($result->disposition)->toBe(AffiliateOutcomeDisposition::Created)
        ->and($result->conversion)->not->toBeNull()
        ->and($result->conversion?->affiliateCode)->toBe($this->affiliate->code)
        ->and($result->conversion?->conversionType)->toBe('event_checkin');
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

        $link = app(CreateTrackingLink::class)->handle((string) $this->affiliate->getKey(), 'https://ilmu360.test/majlis/ramadan', [
            'program_id' => $program->getKey(),
        ]);

        expect($link->program_id)->toBe((string) $program->getKey())
            ->and($link->tracking_url)->toContain('/go/')
            ->and(AffiliateLink::query()->whereKey($link->getKey())->exists())->toBeTrue()
            ->and($link->trackedLink()->exists())->toBeTrue();
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
        expect(fn () => app(CreateTrackingLink::class)->handle((string) $this->affiliate->getKey(), 'https://ilmu360.test/majlis/ramadan', [
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
            expect(fn () => app(CreateTrackingLink::class)->handle((string) $affiliateA->getKey(), 'https://merchant.example/summer', [
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

test('it counts only human clicks associated with the public tracking link', function (): void {
    config(['affiliates.links.allowed_hosts' => ['ilmu360.test']]);
    $link = app(CreateTrackingLink::class)->handle((string) $this->affiliate->getKey(), 'https://ilmu360.test/event');
    $tracked = $link->trackedLink()->firstOrFail();
    $listener = app(IncrementAffiliateLinkClicks::class);

    $listener->handle(new LinkClicked($tracked, new LinkClick(['link_id' => (string) Str::uuid()])));
    $listener->handle(new LinkClicked($tracked, new LinkClick(['link_id' => $tracked->getKey(), 'is_bot' => true])));
    $forged = clone $tracked;
    $forged->setAttribute($forged->getKeyName(), (string) Str::uuid());
    $listener->handle(new LinkClicked($forged, new LinkClick(['link_id' => $forged->getKey()])));
    expect(app(AffiliateLinkGate::class)->blockedReason($tracked))->toBeNull()
        ->and(app(AffiliateLinkGate::class)->blockedReason($forged))->toBe('link_unknown');
    $forged->subject_id = 'malformed-subject';
    expect(app(AffiliateLinkGate::class)->blockedReason($forged))->toBe('link_unknown');

    expect($link->refresh()->clicks)->toBe(0);

    $listener->handle(new LinkClicked($tracked, new LinkClick(['link_id' => $tracked->getKey()])));

    expect($link->refresh()->clicks)->toBe(1);
});

test('tracking link commands reject malformed program references and null affiliate reassignment', function (): void {
    config(['affiliates.links.allowed_hosts' => ['ilmu360.test']]);
    $links = app(TrackingLinkAdministration::class);
    $destination = 'https://ilmu360.test/event';
    $affiliateId = (string) $this->affiliate->getKey();
    foreach (['invalid-uuid', [], 123] as $invalid) {
        expect(fn () => $links->create($affiliateId, $destination, ['program_id' => $invalid]))->toThrow(ValidationException::class);
    }
    $link = $links->create($affiliateId, $destination);
    expect(fn () => $links->update($link->id, ['program_id' => 'invalid-uuid']))->toThrow(ValidationException::class)
        ->and(fn () => $links->update($link->id, ['affiliate_id' => null]))->toThrow(ValidationException::class);
    expect(AffiliateLink::query()->findOrFail($link->id)->affiliate_id)->toBe($affiliateId);
});

test('link administration persists deactivation and preserves an existing disable timestamp', function (): void {
    config(['affiliates.links.allowed_hosts' => ['ilmu360.test']]);
    $links = app(TrackingLinkAdministration::class);
    $created = $links->create((string) $this->affiliate->getKey(), 'https://ilmu360.test/event');
    $links->update($created->id, ['deactivated_at' => '2024-01-01 12:00:00']);
    $link = AffiliateLink::query()->findOrFail($created->id);
    expect($link->isEnabled())->toBeFalse();
    $timestamp = $link->deactivated_at;
    $links->setEnabled($created->id, false);
    expect($link->refresh()->deactivated_at?->equalTo($timestamp))->toBeTrue();
    $links->update($created->id, ['deactivated_at' => null]);
    expect($link->refresh()->isEnabled())->toBeTrue();
    expect(fn () => $links->update($created->id, ['deactivated_at' => 'invalid-date']))->toThrow(ValidationException::class);
});
