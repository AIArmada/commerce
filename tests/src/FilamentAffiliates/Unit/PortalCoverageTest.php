<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Models\AffiliateSupportMessage;
use AIArmada\Affiliates\Models\AffiliateSupportTicket;
use AIArmada\Affiliates\Models\AffiliateTaxDocument;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\Support\Links\AffiliateLinkGenerator;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalConversions;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalDashboard;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalLinks;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalPayouts;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalProfile;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalPrograms;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalRegistration;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalSupport;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['affiliates.owner.enabled' => false]);

    AffiliateProgramCreative::query()->delete();
    AffiliateProgramMembership::query()->delete();
    AffiliateProgram::query()->delete();
    AffiliateSupportMessage::query()->delete();
    AffiliateSupportTicket::query()->delete();
    AffiliateTaxDocument::query()->delete();
    AffiliatePayout::query()->delete();
    AffiliateConversion::query()->delete();
    AffiliateAttribution::query()->delete();
    Affiliate::query()->delete();
    User::query()->delete();
});

afterEach(function (): void {
    config(['affiliates.owner.enabled' => false]);
});

it('portal pages do not leak cross-tenant data when owner mode enabled', function (): void {
    config([
        'affiliates.owner.enabled' => true,
    ]);

    $ownerA = User::create([
        'name' => 'Owner A',
        'email' => 'owner-a-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::create([
        'name' => 'Owner B',
        'email' => 'owner-b-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $affiliateB = Affiliate::create([
        'code' => 'PORTAL-B-' . Str::uuid(),
        'name' => 'Portal Affiliate B',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => (string) $ownerB->getKey(),
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliateB->getKey(),
        'affiliate_code' => $affiliateB->code,
        'order_reference' => 'ORDER-B-001',
        'total_minor' => 10000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);

    AffiliatePayout::create([
        'reference' => 'PAYOUT-B-' . Str::uuid(),
        'status' => CompletedPayout::class,
        'total_minor' => 1500,
        'currency' => 'USD',
        'payee_type' => $affiliateB->getMorphClass(),
        'payee_id' => $affiliateB->getKey(),
        'paid_at' => now(),
    ]);

    $dashboard = new PortalDashboard;
    $dashboardData = $dashboard->getViewData();

    expect($dashboardData['hasAffiliate'])->toBeFalse()
        ->and($dashboardData['totalClicks'])->toBe(0)
        ->and($dashboardData['totalConversions'])->toBe(0)
        ->and($dashboardData['totalEarnings'])->toBe(0)
        ->and($dashboardData['pendingEarnings'])->toBe(0);

    $conversions = new PortalConversions;
    $conversionsData = $conversions->getViewData();

    expect($conversionsData['hasAffiliate'])->toBeFalse()
        ->and($conversionsData['totalConversions'])->toBe(0)
        ->and($conversionsData['totalEarnings'])->toBe(0)
        ->and($conversionsData['pendingEarnings'])->toBe(0);

    $payouts = new PortalPayouts;
    $payoutsData = $payouts->getViewData();

    expect($payoutsData['hasAffiliate'])->toBeFalse()
        ->and($payoutsData['totalPaid'])->toBe(0);
});

it('portal pages only return current owner affiliate stats when multiple owners exist', function (): void {
    config([
        'affiliates.owner.enabled' => true,
    ]);

    $ownerA = User::create([
        'name' => 'Owner A',
        'email' => 'owner-a2-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::create([
        'name' => 'Owner B',
        'email' => 'owner-b2-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $affiliateA = Affiliate::create([
        'code' => 'PORTAL-A-' . Str::uuid(),
        'name' => 'Portal Affiliate A',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => (string) $ownerA->getKey(),
    ]);

    $affiliateB = Affiliate::create([
        'code' => 'PORTAL-B2-' . Str::uuid(),
        'name' => 'Portal Affiliate B',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => (string) $ownerB->getKey(),
    ]);

    AffiliateAttribution::create([
        'affiliate_id' => $affiliateA->getKey(),
        'affiliate_code' => $affiliateA->code,
        'cart_instance' => 'default',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => (string) $ownerA->getKey(),
    ]);

    AffiliateAttribution::create([
        'affiliate_id' => $affiliateB->getKey(),
        'affiliate_code' => $affiliateB->code,
        'cart_instance' => 'default',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => (string) $ownerB->getKey(),
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliateA->getKey(),
        'affiliate_code' => $affiliateA->code,
        'order_reference' => 'ORDER-A-001',
        'total_minor' => 10000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliateB->getKey(),
        'affiliate_code' => $affiliateB->code,
        'order_reference' => 'ORDER-B-001',
        'total_minor' => 10000,
        'commission_minor' => 9999,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);

    AffiliatePayout::create([
        'reference' => 'PAYOUT-A-' . Str::uuid(),
        'status' => CompletedPayout::class,
        'total_minor' => 1500,
        'currency' => 'USD',
        'payee_type' => $affiliateA->getMorphClass(),
        'payee_id' => $affiliateA->getKey(),
        'paid_at' => now(),
    ]);

    AffiliatePayout::create([
        'reference' => 'PAYOUT-B-' . Str::uuid(),
        'status' => CompletedPayout::class,
        'total_minor' => 9999,
        'currency' => 'USD',
        'payee_type' => $affiliateB->getMorphClass(),
        'payee_id' => $affiliateB->getKey(),
        'paid_at' => now(),
    ]);

    $this->actingAs($ownerA);

    $dashboard = new PortalDashboard;
    $dashboardData = $dashboard->getViewData();

    expect($dashboardData['hasAffiliate'])->toBeTrue()
        ->and($dashboardData['totalClicks'])->toBe(1)
        ->and($dashboardData['totalConversions'])->toBe(1)
        ->and($dashboardData['totalEarnings'])->toBe(1000);

    $payouts = new PortalPayouts;
    $payoutsData = $payouts->getViewData();

    expect($payoutsData['hasAffiliate'])->toBeTrue()
        ->and($payoutsData['totalPaid'])->toBe(1500);
});

it('portal pages return scoped view data when affiliate exists', function (): void {
    $user = User::create([
        'name' => 'Affiliate User',
        'email' => 'affiliate-user@example.com',
        'password' => 'secret',
    ]);

    $affiliate = Affiliate::create([
        'code' => 'PORTAL-' . Str::uuid(),
        'name' => 'Portal Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    AffiliateAttribution::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'cart_instance' => 'default',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORDER-001',
        'total_minor' => 10000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORDER-002',
        'total_minor' => 5000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => PendingConversion::class,
        'occurred_at' => now(),
    ]);

    AffiliatePayout::create([
        'reference' => 'PAYOUT-' . Str::uuid(),
        'status' => CompletedPayout::class,
        'total_minor' => 1500,
        'currency' => 'USD',
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
        'paid_at' => now(),
    ]);

    $this->actingAs($user);

    $dashboard = new PortalDashboard;
    $dashboardData = $dashboard->getViewData();

    expect($dashboardData['hasAffiliate'])->toBeTrue()
        ->and($dashboardData['totalClicks'])->toBe(1)
        ->and($dashboardData['totalConversions'])->toBe(2)
        ->and($dashboardData['totalEarnings'])->toBe(1000)
        ->and($dashboardData['pendingEarnings'])->toBe(500);

    $conversions = new PortalConversions;
    $conversionsData = $conversions->getViewData();

    expect($conversionsData['hasAffiliate'])->toBeTrue()
        ->and($conversionsData['totalConversions'])->toBe(2)
        ->and($conversionsData['totalEarnings'])->toBe(1000)
        ->and($conversionsData['pendingEarnings'])->toBe(500);

    $payouts = new PortalPayouts;
    $payoutsData = $payouts->getViewData();

    expect($payoutsData['hasAffiliate'])->toBeTrue()
        ->and($payoutsData['totalPaid'])->toBe(1500);
});

it('portal dashboard state objects expose label and color helpers for badges', function (): void {
    $user = User::create([
        'name' => 'Portal Render User',
        'email' => 'portal-render-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);

    $affiliate = Affiliate::create([
        'code' => 'PORTAL-RENDER-' . Str::uuid(),
        'name' => 'Portal Render Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORDER-RENDER-001',
        'total_minor' => 5000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => PendingConversion::class,
        'occurred_at' => now(),
    ]);

    expect($affiliate->status->label())->toBe('Active')
        ->and($affiliate->status->color())->toBe('success')
        ->and($conversion->status->label())->toBe('Pending Review')
        ->and($conversion->status->color())->toBe('warning');
});

it('PortalConversions configures its table', function (): void {
    $user = User::create([
        'name' => 'Conversions User',
        'email' => 'conversions-user-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);

    $affiliate = Affiliate::create([
        'code' => 'CONV-' . Str::uuid(),
        'name' => 'Conversions Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    $this->actingAs($user);

    $table = Mockery::mock(Table::class);
    $table->shouldReceive('query')->once()->andReturnSelf();
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('defaultSort')->once()->andReturnSelf();
    $table->shouldReceive('paginated')->once()->andReturnSelf();

    $page = new PortalConversions;
    $page->table($table);

    expect(true)->toBeTrue();
});

it('PortalConversions uses neutral reference and total columns', function (): void {
    $repositoryRoot = dirname(__DIR__, 4);
    $source = file_get_contents($repositoryRoot . '/packages/filament-affiliates/src/Pages/Portal/PortalConversions.php');

    expect($source)
        ->toContain("TextColumn::make('external_reference')")
        ->toContain("->label(__('Reference'))")
        ->toContain("TextColumn::make('value_minor')")
        ->toContain("->label(__('Total'))");
});

it('PortalPayouts configures its table', function (): void {
    $user = User::create([
        'name' => 'Payouts User',
        'email' => 'payouts-user-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);

    $affiliate = Affiliate::create([
        'code' => 'PAY-' . Str::uuid(),
        'name' => 'Payouts Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    $this->actingAs($user);

    $table = Mockery::mock(Table::class);
    $table->shouldReceive('query')->once()->andReturnSelf();
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('defaultSort')->once()->andReturnSelf();
    $table->shouldReceive('paginated')->once()->andReturnSelf();

    $page = new PortalPayouts;
    $page->table($table);

    expect(true)->toBeTrue();
});

it('PortalLinks generates links when affiliate exists', function (): void {
    $user = User::create([
        'name' => 'Link User',
        'email' => 'link-user@example.com',
        'password' => 'secret',
    ]);

    $affiliate = Affiliate::create([
        'code' => 'LINK-' . Str::uuid(),
        'name' => 'Link Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    $this->actingAs($user);

    $this->app->instance(AffiliateLinkGenerator::class, new class
    {
        public function generate(string $affiliateCode, string $url): string
        {
            return $url . '?aff=' . $affiliateCode;
        }
    });

    $page = new PortalLinks;
    $page->mount();

    expect($page->getDefaultLink())->toContain($affiliate->code);

    $page->targetUrl = url('/test');
    $page->generateLink();

    expect($page->generatedLink)->toBe(url('/test') . '?aff=' . $affiliate->code);

    $reflection = new ReflectionClass($page);
    $method = $reflection->getMethod('getHeaderActions');

    $actions = $method->invoke($page);
    expect($actions)->toBeArray()->and(count($actions))->toBe(1);
});

it('PortalLinks falls back when link generator rejects the default URL', function (): void {
    $user = User::create([
        'name' => 'Fallback User',
        'email' => 'fallback-user@example.com',
        'password' => 'secret',
    ]);

    Affiliate::create([
        'code' => 'FALLBACK-' . Str::uuid(),
        'name' => 'Fallback Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    $this->actingAs($user);

    $this->app->instance(AffiliateLinkGenerator::class, new class
    {
        public function generate(string $affiliateCode, string $url): string
        {
            throw new InvalidArgumentException('Disallowed URL');
        }
    });

    $page = new PortalLinks;

    $fallback = $page->getDefaultLink();

    expect($fallback)->toContain('?');
});

it('PortalRegistration blocks register when disabled', function (): void {
    $registration = new PortalRegistration;

    $reflection = new ReflectionClass($registration);
    $enabled = $reflection->getProperty('registrationEnabled');
    $enabled->setValue($registration, false);

    expect($registration->register())->toBeNull();
});

it('PortalRegistration subheading reflects approval mode', function (): void {
    $registration = new PortalRegistration;

    $reflection = new ReflectionClass($registration);

    $enabled = $reflection->getProperty('registrationEnabled');
    $enabled->setValue($registration, true);

    $mode = $reflection->getProperty('approvalMode');

    $mode->setValue($registration, 'auto');
    expect($registration->getSubheading())->toContain('automatically');

    $mode->setValue($registration, 'open');
    expect($registration->getSubheading())->toContain('pending');

    $mode->setValue($registration, 'admin');
    expect($registration->getSubheading())->toContain('reviewed');
});

it('PortalProfile updates affiliate profile and default payout method', function (): void {
    $user = User::create([
        'name' => 'Portal Profile User',
        'email' => 'portal-profile-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);

    $affiliate = Affiliate::create([
        'code' => 'PROFILE-' . Str::uuid(),
        'name' => 'Original Affiliate Name',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    $this->actingAs($user);

    $page = new PortalProfile;
    $page->mount();

    $page->name = 'Updated Affiliate Name';
    $page->contactEmail = 'updated-affiliate@example.com';
    $page->websiteUrl = 'https://affiliate.example.com';
    $page->payoutMethodType = 'paypal';
    $page->payoutMethodLabel = 'Primary PayPal';
    $page->payoutMethodAccountRef = 'payouts@example.com';

    $page->saveProfile();

    $affiliate->refresh();

    $payoutMethod = AffiliatePayoutMethod::query()
        ->where('affiliate_id', $affiliate->getKey())
        ->where('is_default', true)
        ->first();

    expect($affiliate->name)->toBe('Updated Affiliate Name')
        ->and($affiliate->contact_email)->toBe('updated-affiliate@example.com')
        ->and($affiliate->website_url)->toBe('https://affiliate.example.com')
        ->and($payoutMethod)->not->toBeNull()
        ->and($payoutMethod?->type->value)->toBe('paypal')
        ->and($payoutMethod?->details['label'] ?? null)->toBe('Primary PayPal')
        ->and($payoutMethod?->details['account_ref'] ?? null)->toBe('payouts@example.com');
});

it('PortalPrograms returns joined programs and creative assets for the current affiliate', function (): void {
    $user = User::create([
        'name' => 'Portal Programs User',
        'email' => 'portal-programs-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);

    $affiliate = Affiliate::create([
        'code' => 'PROGRAMS-' . Str::uuid(),
        'name' => 'Programs Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    $program = AffiliateProgram::create([
        'name' => 'Starter Program',
        'slug' => 'starter-program-' . Str::uuid(),
        'status' => 'active',
        'default_commission_rate_basis_points' => 500,
        'commission_type' => 'percentage',
        'cookie_lifetime_days' => 30,
        'visibility' => 'public',
        'requires_approval' => false,
    ]);

    AffiliateProgramMembership::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'program_id' => $program->getKey(),
        'status' => 'approved',
        'applied_at' => now(),
        'approved_at' => now(),
    ]);

    AffiliateProgramCreative::create([
        'program_id' => $program->getKey(),
        'type' => 'banner',
        'name' => 'Hero Banner',
        'asset_url' => 'https://cdn.example.com/banner.jpg',
        'destination_url' => 'https://example.com/offer',
        'tracking_code' => 'trk-hero-banner',
    ]);

    $this->actingAs($user);

    $page = new PortalPrograms;
    $viewData = $page->getViewData();

    expect($viewData['hasAffiliate'])->toBeTrue()
        ->and($viewData['creativeCount'])->toBe(1)
        ->and($viewData['programs'])->toHaveCount(1)
        ->and($viewData['programs'][0]['name'])->toBe('Starter Program')
        ->and($viewData['programs'][0]['is_joined'])->toBeTrue()
        ->and($viewData['programs'][0]['creative_count'])->toBe(1)
        ->and($viewData['programs'][0]['creatives'][0]['name'])->toBe('Hero Banner');
});

it('PortalPrograms can join available programs and expose accessible creative assets', function (): void {
    $user = User::create([
        'name' => 'Portal Join User',
        'email' => 'portal-join-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);

    $affiliate = Affiliate::create([
        'code' => 'JOIN-' . Str::uuid(),
        'name' => 'Join Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    $program = AffiliateProgram::create([
        'name' => 'Joinable Program',
        'slug' => 'joinable-program-' . Str::uuid(),
        'status' => 'active',
        'default_commission_rate_basis_points' => 500,
        'commission_type' => 'percentage',
        'cookie_lifetime_days' => 30,
        'visibility' => 'public',
        'requires_approval' => false,
    ]);

    AffiliateProgramCreative::create([
        'program_id' => $program->getKey(),
        'type' => 'banner',
        'name' => 'Join Banner',
        'asset_url' => 'https://cdn.example.com/join-banner.jpg',
        'destination_url' => 'https://example.com/join-offer',
        'tracking_code' => 'trk-join-banner',
    ]);

    $this->actingAs($user);

    $page = new PortalPrograms;

    $viewData = $page->getViewData();

    expect($viewData['programs'])->toHaveCount(1)
        ->and($viewData['programs'][0]['can_join'])->toBeTrue()
        ->and($viewData['programs'][0]['is_joined'])->toBeFalse()
        ->and($viewData['programs'][0]['creative_count'])->toBe(0)
        ->and($viewData['programs'][0]['creatives'])->toBe([]);

    $page->joinProgram((string) $program->getKey());

    $membership = AffiliateProgramMembership::query()
        ->where('affiliate_id', $affiliate->getKey())
        ->where('program_id', $program->getKey())
        ->first();

    expect($membership)->not->toBeNull()
        ->and($membership?->status)->toBe(MembershipStatus::Approved);

    $updatedViewData = $page->getViewData();

    expect($updatedViewData['programs'][0]['is_joined'])->toBeTrue()
        ->and($updatedViewData['programs'][0]['creative_count'])->toBe(1)
        ->and($updatedViewData['programs'][0]['creatives'][0]['name'])->toBe('Join Banner');
});

it('PortalPrograms marks approval-required programs as pending requests', function (): void {
    $user = User::create([
        'name' => 'Portal Pending User',
        'email' => 'portal-pending-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);

    $affiliate = Affiliate::create([
        'code' => 'PENDING-' . Str::uuid(),
        'name' => 'Pending Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    $program = AffiliateProgram::create([
        'name' => 'Approval Program',
        'slug' => 'approval-program-' . Str::uuid(),
        'status' => 'active',
        'default_commission_rate_basis_points' => 500,
        'commission_type' => 'percentage',
        'cookie_lifetime_days' => 30,
        'visibility' => 'public',
        'requires_approval' => true,
    ]);

    $this->actingAs($user);

    $page = new PortalPrograms;
    $page->joinProgram((string) $program->getKey());

    $membership = AffiliateProgramMembership::query()
        ->where('affiliate_id', $affiliate->getKey())
        ->where('program_id', $program->getKey())
        ->first();

    expect($membership)->not->toBeNull()
        ->and($membership?->status)->toBe(MembershipStatus::Pending);
});

it('PortalSupport can create tickets, reply to them, and track compliance documents', function (): void {
    $user = User::create([
        'name' => 'Portal Support User',
        'email' => 'portal-support-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);

    $affiliate = Affiliate::create([
        'code' => 'SUPPORT-' . Str::uuid(),
        'name' => 'Support Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    $this->actingAs($user);

    $page = new PortalSupport;
    $page->subject = 'Payout question';
    $page->category = 'billing';
    $page->priority = 'high';
    $page->message = 'Can you clarify the latest payout status?';
    $page->createTicket();

    $ticket = AffiliateSupportTicket::query()
        ->where('affiliate_id', $affiliate->getKey())
        ->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket?->subject)->toBe('Payout question')
        ->and($ticket?->messages()->count())->toBe(1);

    $page->replyMessages[(string) $ticket->getKey()] = 'Thanks, please review this ticket.';
    $page->replyToTicket((string) $ticket->getKey());

    expect($ticket->messages()->count())->toBe(2);

    AffiliateTaxDocument::create([
        'affiliate_id' => $affiliate->getKey(),
        'document_type' => '1099',
        'tax_year' => (int) now()->format('Y'),
        'status' => 'generated',
        'total_amount_minor' => 250000,
        'currency' => 'USD',
        'generated_at' => now(),
        'notes' => 'Ready for review',
    ]);

    $viewData = $page->getViewData();

    expect($viewData['hasAffiliate'])->toBeTrue()
        ->and($viewData['tickets'])->toHaveCount(1)
        ->and($viewData['tickets'][0]['messages'])->toHaveCount(2)
        ->and($viewData['taxDocuments'])->toHaveCount(1)
        ->and($viewData['taxDocuments'][0]['document_type'])->toBe('1099');
});
