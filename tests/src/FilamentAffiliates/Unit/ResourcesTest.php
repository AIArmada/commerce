<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateCommissionTemplate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliateNetwork;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\Affiliates\Models\AffiliateRankHistory;
use AIArmada\Affiliates\Models\AffiliateSupportTicket;
use AIArmada\Affiliates\Models\AffiliateTaxDocument;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAffiliates\Resources\AffiliateCommissionTemplateResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateFraudSignalResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateLinkResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateNetworkResource;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\RelationManagers\PayoutEventsRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\RelationManagers\CommissionPromotionsRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\RelationManagers\CommissionRulesRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\RelationManagers\CreativesRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\RelationManagers\MembershipsRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\RelationManagers\TiersRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateRankHistoryResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateRankResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers\ConversionsRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers\PayoutHoldsRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers\PayoutMethodsRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers\PayoutsRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers\ProgramsRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers\VouchersRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateSupportTicketResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateSupportTicketResource\RelationManagers\MessagesRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateTaxDocumentResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateTouchpointResource;
use AIArmada\CommerceSupport\Models\Permission;
use Illuminate\Support\Str;

beforeEach(function (): void {
    User::query()->delete();
    Permission::query()->delete();
});

// AffiliateResource Tests
it('AffiliateResource has correct model', function (): void {
    expect(AffiliateResource::getModel())->toBe(Affiliate::class);
});

it('AffiliateResource returns pages array', function (): void {
    $pages = AffiliateResource::getPages();

    expect($pages)
        ->toBeArray()
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('view')
        ->toHaveKey('edit');
});

it('AffiliateResource has relations', function (): void {
    $relations = AffiliateResource::getRelations();

    expect($relations)
        ->toBeArray()
        ->toBe([
            ConversionsRelationManager::class,
            ProgramsRelationManager::class,
            PayoutsRelationManager::class,
            PayoutMethodsRelationManager::class,
            PayoutHoldsRelationManager::class,
            VouchersRelationManager::class,
        ]);
});

it('AffiliateResource has navigation sort from config', function (): void {
    config(['filament-affiliates.resources.navigation_sort.affiliates' => 60]);

    expect(AffiliateResource::getNavigationSort())->toBe(60);
});

it('AffiliateResource has navigation group from config', function (): void {
    config(['filament-affiliates.navigation_group' => 'Affiliates']);

    expect(AffiliateResource::getNavigationGroup())->toBe('Affiliates');
});

it('AffiliateResource CRUD abilities follow affiliate permission set', function (): void {
    $user = User::create([
        'name' => 'Affiliate CRUD Operator',
        'email' => 'affiliate-crud-operator@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    expect(AffiliateResource::canViewAny())->toBeFalse()
        ->and(AffiliateResource::canCreate())->toBeFalse();

    Permission::create(['name' => 'affiliate.viewAny', 'guard_name' => 'web']);
    Permission::create(['name' => 'affiliate.create', 'guard_name' => 'web']);
    $user->givePermissionTo('affiliate.viewAny');
    $user->givePermissionTo('affiliate.create');

    expect(AffiliateResource::canViewAny())->toBeTrue()
        ->and(AffiliateResource::canCreate())->toBeTrue();
});

// AffiliateConversionResource Tests
it('AffiliateConversionResource has correct model', function (): void {
    expect(AffiliateConversionResource::getModel())->toBe(AffiliateConversion::class);
});

it('AffiliateConversionResource returns pages array', function (): void {
    $pages = AffiliateConversionResource::getPages();

    expect($pages)
        ->toBeArray()
        ->toHaveKey('index')
        ->toHaveKey('view');
});

it('AffiliateConversionResource has empty relations', function (): void {
    expect(AffiliateConversionResource::getRelations())->toBeArray()->toBeEmpty();
});

it('AffiliateConversionResource is explicitly non-CRUD', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Contract Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'ORDER-' . Str::uuid(),
        'subject_identifier' => 'checkout:' . Str::uuid(),
        'status' => PendingConversion::class,
        'occurred_at' => now(),
        'subtotal_minor' => 10000,
        'total_minor' => 10000,
        'value_minor' => 10000,
        'commission_minor' => 1000,
        'currency' => 'USD',
        'commission_currency' => 'USD',
    ]);

    expect(AffiliateConversionResource::canCreate())->toBeFalse()
        ->and(AffiliateConversionResource::canEdit($conversion))->toBeFalse()
        ->and(AffiliateConversionResource::canDelete($conversion))->toBeFalse();
});

it('AffiliateConversionResource has navigation sort from config', function (): void {
    config(['filament-affiliates.resources.navigation_sort.affiliate_conversions' => 61]);

    expect(AffiliateConversionResource::getNavigationSort())->toBe(61);
});

it('AffiliateConversionResource canViewAny allows affiliate_conversion.update permission', function (): void {
    $user = User::create([
        'name' => 'Conversion Update Viewer',
        'email' => 'conversion-update-viewer@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    expect(AffiliateConversionResource::canViewAny())->toBeFalse();

    Permission::create(['name' => 'affiliate_conversion.update', 'guard_name' => 'web']);
    $user->givePermissionTo('affiliate_conversion.update');

    expect(AffiliateConversionResource::canViewAny())->toBeTrue();
});

it('AffiliateConversionResource canViewAny allows affiliate.approve permission', function (): void {
    $user = User::create([
        'name' => 'Conversion Approve Viewer',
        'email' => 'conversion-approve-viewer@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    expect(AffiliateConversionResource::canViewAny())->toBeFalse();

    Permission::create(['name' => 'affiliate.approve', 'guard_name' => 'web']);
    $user->givePermissionTo('affiliate.approve');

    expect(AffiliateConversionResource::canViewAny())->toBeTrue();
});

it('AffiliateCommissionTemplateResource has correct model', function (): void {
    expect(AffiliateCommissionTemplateResource::getModel())->toBe(AffiliateCommissionTemplate::class);
});

it('AffiliateCommissionTemplateResource returns pages array', function (): void {
    expect(AffiliateCommissionTemplateResource::getPages())
        ->toBeArray()
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('view')
        ->toHaveKey('edit');
});

it('AffiliateRankHistoryResource has correct model', function (): void {
    expect(AffiliateRankHistoryResource::getModel())->toBe(AffiliateRankHistory::class);
});

it('AffiliateRankHistoryResource returns pages array', function (): void {
    expect(AffiliateRankHistoryResource::getPages())
        ->toBeArray()
        ->toHaveKey('index')
        ->toHaveKey('view');
});

it('AffiliateSupportTicketResource has correct model', function (): void {
    expect(AffiliateSupportTicketResource::getModel())->toBe(AffiliateSupportTicket::class);
});

it('AffiliateSupportTicketResource returns pages array', function (): void {
    expect(AffiliateSupportTicketResource::getPages())
        ->toBeArray()
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('view')
        ->toHaveKey('edit');
});

it('AffiliateSupportTicketResource has messages relation manager', function (): void {
    expect(AffiliateSupportTicketResource::getRelations())
        ->toBeArray()
        ->toBe([MessagesRelationManager::class]);
});

it('AffiliateTaxDocumentResource has correct model', function (): void {
    expect(AffiliateTaxDocumentResource::getModel())->toBe(AffiliateTaxDocument::class);
});

it('AffiliateTaxDocumentResource returns pages array', function (): void {
    expect(AffiliateTaxDocumentResource::getPages())
        ->toBeArray()
        ->toHaveKey('index')
        ->toHaveKey('view');
});

// AffiliatePayoutResource Tests
it('AffiliatePayoutResource has correct model', function (): void {
    expect(AffiliatePayoutResource::getModel())->toBe(AffiliatePayout::class);
});

it('AffiliatePayoutResource returns pages array', function (): void {
    $pages = AffiliatePayoutResource::getPages();

    expect($pages)
        ->toBeArray()
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('view');
});

it('AffiliatePayoutResource has relations', function (): void {
    $relations = AffiliatePayoutResource::getRelations();

    expect($relations)
        ->toBeArray()
        ->toBe([
            AffiliatePayoutResource\RelationManagers\ConversionsRelationManager::class,
            PayoutEventsRelationManager::class,
        ]);
});

it('AffiliatePayoutResource is explicitly non-CRUD', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Payout Contract Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'reference' => 'PAY-' . Str::uuid(),
        'status' => PendingPayout::class,
        'total_minor' => 5000,
        'currency' => 'USD',
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
    ]);

    expect(AffiliatePayoutResource::canCreate())->toBeFalse()
        ->and(AffiliatePayoutResource::canEdit($payout))->toBeFalse()
        ->and(AffiliatePayoutResource::canDelete($payout))->toBeFalse();
});

it('AffiliatePayoutResource canCreate allows payout operator abilities', function (): void {
    $user = User::create([
        'name' => 'Payout Creator',
        'email' => 'payout-creator@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    expect(AffiliatePayoutResource::canCreate())->toBeFalse();

    Permission::create(['name' => 'affiliate.payout', 'guard_name' => 'web']);
    $user->givePermissionTo('affiliate.payout');

    expect(AffiliatePayoutResource::canCreate())->toBeTrue();
});

it('AffiliatePayoutResource has navigation sort from config', function (): void {
    config(['filament-affiliates.resources.navigation_sort.affiliate_payouts' => 62]);

    expect(AffiliatePayoutResource::getNavigationSort())->toBe(62);
});

// AffiliateProgramResource Tests
it('AffiliateProgramResource has correct model', function (): void {
    expect(AffiliateProgramResource::getModel())->toBe(AffiliateProgram::class);
});

it('AffiliateProgramResource returns pages array', function (): void {
    $pages = AffiliateProgramResource::getPages();

    expect($pages)
        ->toBeArray()
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('view')
        ->toHaveKey('edit');
});

it('AffiliateProgramResource exposes relation managers', function (): void {
    $relations = AffiliateProgramResource::getRelations();

    expect($relations)
        ->toBeArray()
        ->toContain(TiersRelationManager::class)
        ->toContain(MembershipsRelationManager::class)
        ->toContain(CreativesRelationManager::class)
        ->toContain(CommissionRulesRelationManager::class)
        ->toContain(CommissionPromotionsRelationManager::class);
});

it('AffiliateProgramResource has navigation group from config', function (): void {
    config(['filament-affiliates.navigation_group' => 'Partners']);

    expect(AffiliateProgramResource::getNavigationGroup())->toBe('Partners');
});

it('AffiliateProgramResource has navigation sort from config', function (): void {
    config(['filament-affiliates.resources.navigation_sort.affiliate_programs' => 73]);

    expect(AffiliateProgramResource::getNavigationSort())->toBe(73);
});

it('AffiliateProgramResource CRUD abilities follow affiliate permission set', function (): void {
    $user = User::create([
        'name' => 'Program CRUD Operator',
        'email' => 'program-crud-operator@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    expect(AffiliateProgramResource::canViewAny())->toBeFalse()
        ->and(AffiliateProgramResource::canCreate())->toBeFalse();

    Permission::create(['name' => 'affiliate.viewAny', 'guard_name' => 'web']);
    Permission::create(['name' => 'affiliate.create', 'guard_name' => 'web']);
    $user->givePermissionTo('affiliate.viewAny');
    $user->givePermissionTo('affiliate.create');

    expect(AffiliateProgramResource::canViewAny())->toBeTrue()
        ->and(AffiliateProgramResource::canCreate())->toBeTrue();
});

// AffiliateLinkResource Tests
it('AffiliateLinkResource has correct model', function (): void {
    expect(AffiliateLinkResource::getModel())->toBe(AffiliateLink::class);
});

it('AffiliateLinkResource returns pages array', function (): void {
    $pages = AffiliateLinkResource::getPages();

    expect($pages)
        ->toBeArray()
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('view')
        ->toHaveKey('edit');
});

it('AffiliateLinkResource CRUD abilities follow affiliate permission set', function (): void {
    $user = User::create([
        'name' => 'Link CRUD Operator',
        'email' => 'link-crud-operator@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    expect(AffiliateLinkResource::canViewAny())->toBeFalse()
        ->and(AffiliateLinkResource::canCreate())->toBeFalse();

    Permission::create(['name' => 'affiliate.viewAny', 'guard_name' => 'web']);
    Permission::create(['name' => 'affiliate.create', 'guard_name' => 'web']);
    $user->givePermissionTo('affiliate.viewAny');
    $user->givePermissionTo('affiliate.create');

    expect(AffiliateLinkResource::canViewAny())->toBeTrue()
        ->and(AffiliateLinkResource::canCreate())->toBeTrue();
});

// AffiliateTouchpointResource Tests
it('AffiliateTouchpointResource has correct model', function (): void {
    expect(AffiliateTouchpointResource::getModel())->toBe(AffiliateTouchpoint::class);
});

it('AffiliateTouchpointResource is read-only', function (): void {
    $touchpoint = AffiliateTouchpoint::make();

    expect(AffiliateTouchpointResource::canCreate())->toBeFalse()
        ->and(AffiliateTouchpointResource::canEdit($touchpoint))->toBeFalse()
        ->and(AffiliateTouchpointResource::canDelete($touchpoint))->toBeFalse();
});

it('AffiliateTouchpointResource returns pages array', function (): void {
    expect(AffiliateTouchpointResource::getPages())
        ->toBeArray()
        ->toHaveKey('index')
        ->toHaveKey('view');
});

// AffiliateRankResource Tests
it('AffiliateRankResource has correct model', function (): void {
    expect(AffiliateRankResource::getModel())->toBe(AffiliateRank::class);
});

it('AffiliateRankResource returns pages array', function (): void {
    expect(AffiliateRankResource::getPages())
        ->toBeArray()
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('view')
        ->toHaveKey('edit');
});

it('AffiliateRankResource has navigation sort from config', function (): void {
    config(['filament-affiliates.resources.navigation_sort.affiliate_ranks' => 67]);

    expect(AffiliateRankResource::getNavigationSort())->toBe(67);
});

// AffiliateNetworkResource Tests
it('AffiliateNetworkResource has correct model', function (): void {
    expect(AffiliateNetworkResource::getModel())->toBe(AffiliateNetwork::class);
});

it('AffiliateNetworkResource is read-only', function (): void {
    $network = AffiliateNetwork::make();

    expect(AffiliateNetworkResource::canCreate())->toBeFalse()
        ->and(AffiliateNetworkResource::canEdit($network))->toBeFalse()
        ->and(AffiliateNetworkResource::canDelete($network))->toBeFalse();
});

it('AffiliateNetworkResource returns pages array', function (): void {
    expect(AffiliateNetworkResource::getPages())
        ->toBeArray()
        ->toHaveKey('index')
        ->toHaveKey('view');
});

// AffiliateFraudSignalResource Tests
it('AffiliateFraudSignalResource has correct model', function (): void {
    expect(AffiliateFraudSignalResource::getModel())->toBe(AffiliateFraudSignal::class);
});

it('AffiliateFraudSignalResource returns pages array', function (): void {
    $pages = AffiliateFraudSignalResource::getPages();

    expect($pages)
        ->toBeArray()
        ->toHaveKey('index')
        ->toHaveKey('view');
});

it('AffiliateFraudSignalResource has empty relations', function (): void {
    expect(AffiliateFraudSignalResource::getRelations())->toBeArray()->toBeEmpty();
});

it('AffiliateFraudSignalResource has navigation badge color', function (): void {
    expect(AffiliateFraudSignalResource::getNavigationBadgeColor())->toBe('danger');
});

it('AffiliateFraudSignalResource is explicitly non-CRUD', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Fraud Contract Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $signal = AffiliateFraudSignal::create([
        'affiliate_id' => $affiliate->getKey(),
        'rule_code' => 'velocity',
        'risk_points' => 80,
        'severity' => FraudSeverity::High,
        'description' => 'Contract check signal',
        'status' => FraudSignalStatus::Detected,
        'detected_at' => now(),
    ]);

    expect(AffiliateFraudSignalResource::canCreate())->toBeFalse()
        ->and(AffiliateFraudSignalResource::canEdit($signal))->toBeFalse()
        ->and(AffiliateFraudSignalResource::canDelete($signal))->toBeFalse();
});

it('AffiliateFraudSignalResource has navigation group from config', function (): void {
    config(['filament-affiliates.navigation_group' => 'Partners']);

    expect(AffiliateFraudSignalResource::getNavigationGroup())->toBe('Partners');
});

it('AffiliateFraudSignalResource has navigation sort from config', function (): void {
    config(['filament-affiliates.resources.navigation_sort.affiliate_fraud_signals' => 74]);

    expect(AffiliateFraudSignalResource::getNavigationSort())->toBe(74);
});

it('AffiliateFraudSignalResource returns null badge when no detected signals', function (): void {
    AffiliateFraudSignal::query()->delete();

    expect(AffiliateFraudSignalResource::getNavigationBadge())->toBeNull();
});
