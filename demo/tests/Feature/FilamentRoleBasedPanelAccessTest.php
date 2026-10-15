<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAuthz\Resources\RoleResource;
use AIArmada\FilamentCashier\Pages\BillingDashboard;
use AIArmada\FilamentCashier\Widgets\TotalMrrWidget;
use AIArmada\FilamentCashier\Widgets\TotalSubscribersWidget;
use AIArmada\FilamentTax\Pages\ManageTaxSettings;
use AIArmada\FilamentTax\Widgets\ExpiringExemptionsWidget;
use AIArmada\FilamentTax\Widgets\TaxStatsWidget;
use App\Models\User;
use Database\Seeders\BillingShowcaseSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TaxShowcaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $this->owner = User::query()
        ->where('email', 'admin@commerce.demo')
        ->firstOrFail();

    OwnerContext::withOwner($this->owner, function (): void {
        $this->seed(TaxShowcaseSeeder::class);
        $this->seed(BillingShowcaseSeeder::class);
    });
});

test('customers cannot access the admin panel', function (): void {
    $customer = User::query()
        ->where('email', 'john@example.com')
        ->firstOrFail();

    $this->actingAs($customer)
        ->get('/admin')
        ->assertForbidden();
});

test('finance users see billing and tax widgets on the dashboard', function (): void {
    $finance = User::query()
        ->where('email', 'finance@commerce.demo')
        ->firstOrFail();

    $this->actingAs($finance)
        ->get('/admin')
        ->assertOk()
        ->assertSeeLivewire(TotalMrrWidget::class)
        ->assertSeeLivewire(TotalSubscribersWidget::class)
        ->assertSeeLivewire(TaxStatsWidget::class)
        ->assertSeeLivewire(ExpiringExemptionsWidget::class);
});

test('marketing users can access the dashboard without billing or tax widgets', function (): void {
    $marketing = User::query()
        ->where('email', 'marketing@commerce.demo')
        ->firstOrFail();

    $this->actingAs($marketing)
        ->get('/admin')
        ->assertOk()
        ->assertDontSeeLivewire(TotalMrrWidget::class)
        ->assertDontSeeLivewire(TotalSubscribersWidget::class)
        ->assertDontSeeLivewire(TaxStatsWidget::class)
        ->assertDontSeeLivewire(ExpiringExemptionsWidget::class);
});

test('finance users can access billing and tax management routes', function (): void {
    $finance = User::query()
        ->where('email', 'finance@commerce.demo')
        ->firstOrFail();

    $billingDashboardUrl = BillingDashboard::getUrl(panel: 'admin');
    $taxSettingsUrl = ManageTaxSettings::getUrl(panel: 'admin');

    $this->actingAs($finance)
        ->get($billingDashboardUrl)
        ->assertOk();

    $this->actingAs($finance)
        ->get($taxSettingsUrl)
        ->assertOk();
});

test('marketing users are redirected away from billing and tax management routes', function (): void {
    $marketing = User::query()
        ->where('email', 'marketing@commerce.demo')
        ->firstOrFail();

    $billingDashboardUrl = BillingDashboard::getUrl(panel: 'admin');
    $taxSettingsUrl = ManageTaxSettings::getUrl(panel: 'admin');

    $this->actingAs($marketing)
        ->get($billingDashboardUrl)
        ->assertForbidden();

    $this->actingAs($marketing)
        ->get($taxSettingsUrl)
        ->assertForbidden();
});

test('super admins can access role management', function (): void {
    $superAdmin = User::query()
        ->where('email', 'admin@commerce.demo')
        ->firstOrFail();

    $rolesUrl = RoleResource::getUrl(panel: 'admin');

    $this->actingAs($superAdmin)
        ->get($rolesUrl)
        ->assertOk();
});

test('managers are redirected away from role management', function (): void {
    $manager = User::query()
        ->where('email', 'manager@commerce.demo')
        ->firstOrFail();

    $rolesUrl = RoleResource::getUrl(panel: 'admin');

    $this->actingAs($manager)
        ->get($rolesUrl)
        ->assertForbidden();
});
