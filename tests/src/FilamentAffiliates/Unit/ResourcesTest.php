<?php

declare(strict_types=1);

use AIArmada\Affiliates\Features\Attribution\Actions\CreateTrackingLink;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateTouchpoint;
use AIArmada\Affiliates\Features\Fraud\Api\FraudSeverity;
use AIArmada\Affiliates\Features\Fraud\Api\FraudSignalStatus;
use AIArmada\Affiliates\Features\Fraud\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateUpline;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\Features\Payouts\States\PendingPayout;
use AIArmada\Authz\Features\Authorization\Models\Permission;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAffiliates\Features\Attribution\EntryPoints\Filament\Resources\AffiliateLinkResource;
use AIArmada\FilamentAffiliates\Features\Attribution\EntryPoints\Filament\Resources\AffiliateLinkResource\Pages\EditAffiliateLink;
use AIArmada\FilamentAffiliates\Features\Attribution\EntryPoints\Filament\Resources\AffiliateTouchpointResource;
use AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateCommissionTemplateResource;
use AIArmada\FilamentAffiliates\Features\Compliance\EntryPoints\Filament\Resources\AffiliateSupportTicketResource;
use AIArmada\FilamentAffiliates\Features\Compliance\EntryPoints\Filament\Resources\AffiliateSupportTicketResource\RelationManagers\MessagesRelationManager;
use AIArmada\FilamentAffiliates\Features\Compliance\EntryPoints\Filament\Resources\AffiliateTaxDocumentResource;
use AIArmada\FilamentAffiliates\Features\Conversions\EntryPoints\Filament\Relations\ConversionsRelationManager;
use AIArmada\FilamentAffiliates\Features\Conversions\EntryPoints\Filament\Resources\AffiliateConversionResource;
use AIArmada\FilamentAffiliates\Features\Fraud\EntryPoints\Filament\Resources\AffiliateFraudSignalResource;
use AIArmada\FilamentAffiliates\Features\Identity\EntryPoints\Filament\Resources\AffiliateResource;
use AIArmada\FilamentAffiliates\Features\Identity\EntryPoints\Filament\Resources\AffiliateResource\Pages\EditAffiliate;
use AIArmada\FilamentAffiliates\Features\Identity\EntryPoints\Filament\Resources\AffiliateResource\RelationManagers\VouchersRelationManager;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Relations\PayoutHoldsRelationManager;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Relations\PayoutMethodsRelationManager;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Relations\PayoutsRelationManager;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Resources\AffiliatePayoutResource;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Resources\AffiliatePayoutResource\RelationManagers\PayoutEventsRelationManager;
use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Relations\ProgramsRelationManager;
use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateProgramResource;
use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateProgramResource\RelationManagers\CommissionPromotionsRelationManager;
use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateProgramResource\RelationManagers\CommissionRulesRelationManager;
use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateProgramResource\RelationManagers\CreativesRelationManager;
use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateProgramResource\RelationManagers\MembershipsRelationManager;
use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateProgramResource\RelationManagers\TiersRelationManager;
use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Relations\DownlinesRelationManager;
use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateRankHistoryResource;
use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateRankResource;
use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateUplineResource;
use Illuminate\Support\Str;

beforeEach(function (): void {
    User::query()->where('email', '!=', 'default-owner@example.com')->delete();
    Permission::query()->delete();
});

// AffiliateResource Tests
it('AffiliateResource returns pages array', function (): void {
    $pages = AffiliateResource::getPages();

    expect($pages)

        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('view')
        ->toHaveKey('edit');
});

it('AffiliateResource has relations', function (): void {
    $relations = AffiliateResource::getRelations();

    expect($relations)

        ->toBe([
            DownlinesRelationManager::class,
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
    config(['filament-affiliates.navigation.group' => 'Affiliates']);

    expect(AffiliateResource::getNavigationGroup())->toBe('Affiliates');
});

it('affiliate payout resource shows only commercial-purpose payees', function (): void {
    $commercial = Affiliate::create([
        'code' => 'AFF-PAYOUT-RESOURCE-' . Str::uuid(),
        'name' => 'Commercial Payout Payee',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);
    $dawah = Affiliate::create([
        'code' => 'AFF-PAYOUT-RESOURCE-DAWAH-' . Str::uuid(),
        'name' => 'Dawah Payout Payee',
        'status' => Active::class,
        'purpose' => 'share_member',
        'commission_type' => 'percentage',
        'commission_rate' => 0,
        'currency' => 'USD',
    ]);
    $commercialPayout = AffiliatePayout::create([
        'reference' => 'PAY-COMMERCIAL-' . Str::uuid(),
        'status' => PendingPayout::class,
        'total_minor' => 1000,
        'currency' => 'USD',
        'payee_type' => $commercial->getMorphClass(),
        'payee_id' => $commercial->getKey(),
    ]);
    $dawahPayout = AffiliatePayout::create([
        'reference' => 'PAY-DAWAH-' . Str::uuid(),
        'status' => PendingPayout::class,
        'total_minor' => 1000,
        'currency' => 'USD',
        'payee_type' => $dawah->getMorphClass(),
        'payee_id' => $dawah->getKey(),
    ]);

    $visibleIds = AffiliatePayoutResource::getEloquentQuery()->pluck('id')->map(fn ($id): string => (string) $id)->all();

    expect($visibleIds)->toContain((string) $commercialPayout->getKey())
        ->and($visibleIds)->not->toContain((string) $dawahPayout->getKey());
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

it('prevents deleting affiliates after conversion or payout history exists', function (): void {
    $user = User::create([
        'name' => 'Affiliate Delete Operator',
        'email' => 'affiliate-delete-operator@example.com',
        'password' => bcrypt('password'),
    ]);
    Permission::create(['name' => 'affiliate.delete', 'guard_name' => 'web']);
    $user->givePermissionTo('affiliate.delete');
    $this->actingAs($user);

    $affiliate = Affiliate::create([
        'code' => 'AFF-DELETE-' . Str::uuid(),
        'name' => 'Affiliate With Conversion',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);
    $cleanAffiliate = Affiliate::create([
        'code' => 'AFF-CLEAN-' . Str::uuid(),
        'name' => 'Affiliate Without Financial History',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    expect(AffiliateResource::canDelete($cleanAffiliate))->toBeTrue();

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'DELETE-GUARD-' . Str::uuid(),
        'subject_key' => 'checkout:' . Str::uuid(),
        'status' => PendingConversion::class,
        'occurred_at' => now(),
        'subtotal_minor' => 10000,
        'total_minor' => 10000,
        'value_minor' => 10000,
        'commission_minor' => 1000,
        'currency' => 'USD',
        'commission_currency' => 'USD',
    ]);

    expect(AffiliateResource::canDelete($affiliate))->toBeFalse();

    $payoutAffiliate = Affiliate::create([
        'code' => 'AFF-PAYOUT-' . Str::uuid(),
        'name' => 'Affiliate With Payout History',
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
        'payee_type' => $payoutAffiliate->getMorphClass(),
        'payee_id' => $payoutAffiliate->getKey(),
    ]);

    expect($payout->exists)->toBeTrue()
        ->and(AffiliateResource::canDelete($payoutAffiliate))->toBeFalse();
});

it('rejects forged identity and ownership mutations when saving a Dawah affiliate', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'AFF-DAWAH-EDIT-' . Str::uuid(),
        'name' => 'Dawah Member',
        'status' => Active::class,
        'purpose' => 'share_member',
        'commission_type' => 'percentage',
        'commission_rate' => 0,
        'currency' => 'USD',
    ]);
    $page = new EditAffiliate;
    $method = new ReflectionMethod(EditAffiliate::class, 'handleRecordUpdate');
    $method->setAccessible(true);

    $affiliate->refresh();
    $original = $affiliate->getAttributes();
    $method->invoke($page, $affiliate, [
        'name' => 'Updated member name',
        'handle' => 'forged-handle',
        'apply_template' => (string) Str::uuid(),
        'commission_type' => 'fixed',
        'commission_rate' => 1000,
        'currency' => 'USD',
        'network_depth' => 5,
        'rank_id' => (string) Str::uuid(),
        'parent_affiliate_id' => (string) Str::uuid(),
        'default_voucher_code' => 'FORGED-VOUCHER',
        'tracking_domain' => 'forged.example.test',
        'payout_terms' => 'Net-1',
        'code' => 'FORGED-CODE',
        'purpose' => 'general',
        'metadata' => ['forged' => true],
        'owner_type' => User::class,
        'owner_id' => (string) Str::uuid(),
        'linked_user' => (string) Str::uuid(),
        'status' => 'paused',
    ]);

    $affiliate->refresh();
    expect($affiliate->status->getValue())->toBe('paused');

    foreach (['name', 'handle', 'commission_type', 'commission_rate', 'currency', 'network_depth', 'rank_id', 'parent_affiliate_id', 'default_voucher_code', 'tracking_domain', 'payout_terms', 'code', 'purpose', 'metadata', 'owner_type', 'owner_id'] as $attribute) {
        expect($affiliate->getAttributes()[$attribute] ?? null)->toBe($original[$attribute] ?? null);
    }
});

it('persists allowed commercial affiliate changes through the edit page', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'AFF-COMMERCIAL-EDIT-' . Str::uuid(),
        'name' => 'Commercial Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);
    $parent = Affiliate::create([
        'code' => 'AFF-COMMERCIAL-PARENT-' . Str::uuid(),
        'name' => 'Commercial Parent',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);
    $page = new EditAffiliate;
    $method = new ReflectionMethod(EditAffiliate::class, 'handleRecordUpdate');
    $method->setAccessible(true);

    $data = [
        'name' => 'Updated Commercial Affiliate',
        'handle' => 'updated-commercial',
        'commission_type' => 'fixed',
        'commission_rate' => 1000,
        'currency' => 'MYR',
        'parent_affiliate_id' => (string) $parent->getKey(),
        'default_voucher_code' => 'COMMERCIAL-VOUCHER',
        'tracking_domain' => 'track.example.test',
        'payout_terms' => 'Net-15',
        'status' => 'active',
    ];

    $method->invoke($page, $affiliate, $data);

    $affiliate->refresh();
    expect($affiliate->name)->toBe($data['name'])
        ->and($affiliate->handle)->toBe($data['handle'])
        ->and($affiliate->commission_type->value)->toBe($data['commission_type'])
        ->and($affiliate->commission_rate)->toBe($data['commission_rate'])
        ->and($affiliate->currency)->toBe($data['currency'])
        ->and($affiliate->parent_affiliate_id)->toBe($data['parent_affiliate_id'])
        ->and($affiliate->default_voucher_code)->toBe($data['default_voucher_code'])
        ->and($affiliate->tracking_domain)->toBe($data['tracking_domain'])
        ->and($affiliate->payout_terms)->toBe($data['payout_terms'])
        ->and($affiliate->status->getValue())->toBe($data['status']);
});

it('strips destination edits from a Dawah affiliate link edit payload', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'AFF-DAWAH-LINK-EDIT-' . Str::uuid(),
        'name' => 'Dawah Link Owner',
        'status' => Active::class,
        'purpose' => 'share_member',
        'commission_type' => 'percentage',
        'commission_rate' => 0,
        'currency' => 'USD',
    ]);
    $destinationUrl = mb_rtrim((string) config('app.url'), '/') . '/kongsi';
    $link = app(CreateTrackingLink::class)->handle((string) $affiliate->getKey(), $destinationUrl, [
        'custom_slug' => 'frozenlink',
    ]);
    $frozenTrackingUrl = $link->tracking_url;
    $page = new EditAffiliateLink;
    $method = new ReflectionMethod(EditAffiliateLink::class, 'handleRecordUpdate');
    $method->setAccessible(true);

    $updated = $method->invoke($page, $link, [
        'destination_url' => 'https://attacker.example/',
        'tracking_url' => 'https://attacker.example/tracking',
        'custom_slug' => 'forged-slug',
    ]);

    expect($updated->destination_url)->toBe($destinationUrl)
        ->and($updated->tracking_url)->toBe($frozenTrackingUrl)
        ->and($updated->custom_slug)->toBe('frozenlink');
});

// AffiliateConversionResource Tests
it('AffiliateConversionResource returns pages array', function (): void {
    $pages = AffiliateConversionResource::getPages();

    expect($pages)

        ->toHaveKey('index')
        ->toHaveKey('view');
});

it('AffiliateConversionResource has empty relations', function (): void {
    expect(AffiliateConversionResource::getRelations())->toBeEmpty();
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
        'subject_key' => 'checkout:' . Str::uuid(),
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

it('AffiliateCommissionTemplateResource returns pages array', function (): void {
    expect(AffiliateCommissionTemplateResource::getPages())

        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('view')
        ->toHaveKey('edit');
});

it('AffiliateRankHistoryResource returns pages array', function (): void {
    expect(AffiliateRankHistoryResource::getPages())

        ->toHaveKey('index')
        ->toHaveKey('view');
});

it('AffiliateSupportTicketResource returns pages array', function (): void {
    expect(AffiliateSupportTicketResource::getPages())

        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('view')
        ->toHaveKey('edit');
});

it('AffiliateSupportTicketResource has messages relation manager', function (): void {
    expect(AffiliateSupportTicketResource::getRelations())

        ->toBe([MessagesRelationManager::class]);
});

it('AffiliateTaxDocumentResource returns pages array', function (): void {
    expect(AffiliateTaxDocumentResource::getPages())

        ->toHaveKey('index')
        ->toHaveKey('view');
});

// AffiliatePayoutResource Tests
it('AffiliatePayoutResource has relations', function (): void {
    $relations = AffiliatePayoutResource::getRelations();

    expect($relations)

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

it('AffiliatePayoutResource has navigation sort from config', function (): void {
    config(['filament-affiliates.resources.navigation_sort.affiliate_payouts' => 62]);

    expect(AffiliatePayoutResource::getNavigationSort())->toBe(62);
});

// AffiliateProgramResource Tests
it('AffiliateProgramResource returns pages array', function (): void {
    $pages = AffiliateProgramResource::getPages();

    expect($pages)

        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('view')
        ->toHaveKey('edit');
});

it('AffiliateProgramResource exposes relation managers', function (): void {
    $relations = AffiliateProgramResource::getRelations();

    expect($relations)

        ->toContain(TiersRelationManager::class)
        ->toContain(MembershipsRelationManager::class)
        ->toContain(CreativesRelationManager::class)
        ->toContain(CommissionRulesRelationManager::class)
        ->toContain(CommissionPromotionsRelationManager::class);
});

it('AffiliateProgramResource has navigation group from config', function (): void {
    config(['filament-affiliates.navigation.group' => 'Partners']);

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
it('AffiliateTouchpointResource is read-only', function (): void {
    $touchpoint = new AffiliateTouchpoint;

    expect(AffiliateTouchpointResource::canCreate())->toBeFalse()
        ->and(AffiliateTouchpointResource::canEdit($touchpoint))->toBeFalse()
        ->and(AffiliateTouchpointResource::canDelete($touchpoint))->toBeFalse();
});

it('AffiliateTouchpointResource returns pages array', function (): void {
    expect(AffiliateTouchpointResource::getPages())

        ->toHaveKey('index')
        ->toHaveKey('view');
});

// AffiliateRankResource Tests
it('AffiliateRankResource returns pages array', function (): void {
    expect(AffiliateRankResource::getPages())

        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('view')
        ->toHaveKey('edit');
});

it('AffiliateRankResource has navigation sort from config', function (): void {
    config(['filament-affiliates.resources.navigation_sort.affiliate_ranks' => 67]);

    expect(AffiliateRankResource::getNavigationSort())->toBe(67);
});

// AffiliateUplineResource Tests
it('AffiliateUplineResource is read-only', function (): void {
    $network = new AffiliateUpline;

    expect(AffiliateUplineResource::canCreate())->toBeFalse()
        ->and(AffiliateUplineResource::canEdit($network))->toBeFalse()
        ->and(AffiliateUplineResource::canDelete($network))->toBeFalse();
});

it('AffiliateUplineResource returns pages array', function (): void {
    expect(AffiliateUplineResource::getPages())

        ->toHaveKey('index')
        ->toHaveKey('view');
});

// AffiliateFraudSignalResource Tests
it('AffiliateFraudSignalResource returns pages array', function (): void {
    $pages = AffiliateFraudSignalResource::getPages();

    expect($pages)

        ->toHaveKey('index')
        ->toHaveKey('view');
});

it('AffiliateFraudSignalResource has empty relations', function (): void {
    expect(AffiliateFraudSignalResource::getRelations())->toBeEmpty();
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
    config(['filament-affiliates.navigation.group' => 'Partners']);

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
