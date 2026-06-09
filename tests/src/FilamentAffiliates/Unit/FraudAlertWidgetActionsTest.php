<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\States\Active;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAffiliates\Widgets\FraudAlertWidget;
use AIArmada\CommerceSupport\Models\Permission;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

beforeEach(function (): void {
    AffiliateFraudSignal::query()->delete();
    Affiliate::query()->delete();
    User::query()->delete();
    Permission::query()->delete();
});

it('dismisses fraud alert via widget action when user has affiliate.approve permission', function (): void {
    $user = User::create([
        'name' => 'Fraud Widget Approver',
        'email' => 'fraud-widget-approver@example.com',
        'password' => 'secret',
    ]);

    Permission::create(['name' => 'affiliate.approve', 'guard_name' => 'web']);

    $user->givePermissionTo('affiliate.approve');
    $this->actingAs($user);

    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Fraud Widget Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $signal = AffiliateFraudSignal::create([
        'affiliate_id' => $affiliate->getKey(),
        'rule_code' => 'velocity',
        'risk_points' => 90,
        'severity' => FraudSeverity::Critical,
        'description' => 'Widget dismissal flow',
        'status' => FraudSignalStatus::Detected,
        'detected_at' => now(),
    ]);

    $widget = new FraudAlertWidget;
    $table = $widget->table(Table::make($widget));

    $dismiss = $table->getAction('dismiss');
    expect($dismiss)->not->toBeNull();

    $dismiss?->call(['record' => $signal]);

    $signal->refresh();

    expect($signal->status)->toBe(FraudSignalStatus::Dismissed)
        ->and($signal->reviewed_by)->toBe((string) $user->getAuthIdentifier())
        ->and($signal->reviewed_at)->not->toBeNull();
});

it('blocks fraud alert widget dismiss action when user lacks fraud moderation permissions', function (): void {
    $user = User::create([
        'name' => 'Fraud Widget Unauthorized User',
        'email' => 'fraud-widget-unauthorized@example.com',
        'password' => 'secret',
    ]);

    $this->actingAs($user);

    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Fraud Widget Unauthorized Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $signal = AffiliateFraudSignal::create([
        'affiliate_id' => $affiliate->getKey(),
        'rule_code' => 'pattern',
        'risk_points' => 70,
        'severity' => FraudSeverity::High,
        'description' => 'Unauthorized widget dismissal attempt',
        'status' => FraudSignalStatus::Detected,
        'detected_at' => now(),
    ]);

    $widget = new FraudAlertWidget;
    $table = $widget->table(Table::make($widget));

    $dismiss = $table->getAction('dismiss');
    expect($dismiss)->not->toBeNull();

    expect(fn () => $dismiss?->call(['record' => $signal]))
        ->toThrow(AuthorizationException::class);

    $signal->refresh();

    expect($signal->status)->toBe(FraudSignalStatus::Detected)
        ->and($signal->reviewed_by)->toBeNull()
        ->and($signal->reviewed_at)->toBeNull();
});
