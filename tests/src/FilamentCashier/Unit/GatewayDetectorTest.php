<?php

declare(strict_types=1);

use AIArmada\Cashier\Support\GatewayDetector;
use Illuminate\Support\Collection;

beforeEach(function (): void {
    config()->set('cashier.gateways', [
        'stripe' => [
            'label' => 'Stripe',
            'icon' => 'heroicon-o-credit-card',
            'color' => 'indigo',
            'dashboard_url' => 'https://dashboard.stripe.com',
        ],
        'chip' => [
            'label' => 'CHIP',
            'icon' => 'heroicon-o-cube',
            'color' => 'emerald',
            'dashboard_url' => 'https://gate.chip-in.asia',
        ],
    ]);
});

it('can be instantiated', function (): void {
    $detector = new GatewayDetector;

    expect($detector)->toBeInstanceOf(GatewayDetector::class);
});

it('returns collection for available gateways', function (): void {
    $detector = new GatewayDetector;

    $gateways = $detector->availableGateways();

    expect($gateways)->toBeInstanceOf(Collection::class);
});

it('provides gateway label for stripe', function (): void {
    $detector = new GatewayDetector;

    expect($detector->getLabel('stripe'))->toBe('Stripe');
});

it('provides gateway label for chip', function (): void {
    $detector = new GatewayDetector;

    expect($detector->getLabel('chip'))->toBe('CHIP');
});

it('provides unknown label for unknown gateway', function (): void {
    $detector = new GatewayDetector;

    expect($detector->getLabel('unknown'))->toBe('Unknown');
});

it('provides gateway color for stripe', function (): void {
    $detector = new GatewayDetector;

    expect($detector->getColor('stripe'))->toBe('indigo');
});

it('provides gateway color for chip', function (): void {
    $detector = new GatewayDetector;

    expect($detector->getColor('chip'))->toBe('emerald');
});

it('provides default color for unknown gateway', function (): void {
    $detector = new GatewayDetector;

    expect($detector->getColor('unknown'))->toBe('gray');
});

it('provides gateway icon for stripe', function (): void {
    $detector = new GatewayDetector;

    expect($detector->getIcon('stripe'))->toBe('heroicon-o-credit-card');
});

it('provides gateway icon for chip', function (): void {
    $detector = new GatewayDetector;

    expect($detector->getIcon('chip'))->toBe('heroicon-o-cube');
});

it('provides default icon for unknown gateway', function (): void {
    $detector = new GatewayDetector;

    expect($detector->getIcon('unknown'))->toBe('heroicon-o-cube');
});

it('returns gateway options as array', function (): void {
    $detector = new GatewayDetector;

    $options = $detector->getGatewayOptions();

    expect($options)->toBeArray();
});

it('checks if specific gateway is available', function (): void {
    $detector = new GatewayDetector;

    expect($detector->isAvailable('stripe'))->toBeBool();
    expect($detector->isAvailable('chip'))->toBeBool();
    expect($detector->isAvailable('nonexistent'))->toBe(false);
});

it('provides gateway config for stripe', function (): void {
    $detector = new GatewayDetector;

    $config = $detector->getGatewayConfig('stripe');

    expect($config)->toBeArray()
        ->and($config)->toHaveKeys(['label', 'color', 'icon']);
});

it('provides gateway config for chip', function (): void {
    $detector = new GatewayDetector;

    $config = $detector->getGatewayConfig('chip');

    expect($config)->toBeArray()
        ->and($config)->toHaveKeys(['label', 'color', 'icon']);
});
