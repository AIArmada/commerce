<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentTax\Pages\ManageTaxSettings;
use AIArmada\FilamentTax\Support\FilamentTaxAuthz;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Gate;

uses(TestCase::class);

it('denies access when the user is not authenticated', function (): void {
    auth()->logout();

    expect(FilamentTaxAuthz::check('tax.settings.manage'))->toBeFalse()
        ->and(ManageTaxSettings::canAccess())->toBeFalse();
});

it('denies access when the user lacks permission', function (): void {
    Gate::define('tax.settings.manage', fn (): bool => false);

    $user = User::query()->create([
        'name' => 'No Tax Settings Permission',
        'email' => 'tax-authz-denied@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    expect(FilamentTaxAuthz::check('tax.settings.manage'))->toBeFalse()
        ->and(ManageTaxSettings::canAccess())->toBeFalse();

    $action = FilamentTaxAuthz::requirePermission(Action::make('save'), 'tax.settings.manage');

    expect($action->isAuthorized())->toBeFalse();
});

it('allows access when the user has permission', function (): void {
    Gate::define('tax.settings.manage', fn (): bool => true);

    $user = User::query()->create([
        'name' => 'Tax Settings Manager',
        'email' => 'tax-authz-allowed@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    expect(FilamentTaxAuthz::check('tax.settings.manage'))->toBeTrue()
        ->and(ManageTaxSettings::canAccess())->toBeTrue();

    $action = FilamentTaxAuthz::requirePermission(Action::make('save'), 'tax.settings.manage');

    expect($action->isAuthorized())->toBeTrue();
});
