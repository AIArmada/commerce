<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource;
use AIArmada\Shipping\Models\ReturnAuthorization;

uses(TestCase::class);

// ============================================
// ReturnAuthorizationResource Tests
// ============================================

it('has correct navigation icon', function (): void {
    expect(ReturnAuthorizationResource::getNavigationIcon())->toBe('heroicon-o-arrow-uturn-left');
});

it('has correct navigation group', function (): void {
    expect(ReturnAuthorizationResource::getNavigationGroup())->toBe('Shipping');
});

it('has correct navigation label', function (): void {
    expect(ReturnAuthorizationResource::getNavigationLabel())->toBe('Returns');
});

it('uses return authorization model', function (): void {
    expect(ReturnAuthorizationResource::getModel())->toBe(ReturnAuthorization::class);
});

it('has standard CRUD pages', function (): void {
    $pages = ReturnAuthorizationResource::getPages();

    expect($pages)->toHaveKey('index');
    expect($pages)->toHaveKey('create');
    expect($pages)->toHaveKey('view');
    expect($pages)->toHaveKey('edit');
});
