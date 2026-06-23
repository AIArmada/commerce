<?php

declare(strict_types=1);

use AIArmada\Events\Enums\RegistrationMode;

it('has three cases', function (): void {
    expect(RegistrationMode::cases())->toHaveCount(3);
});

it('identifies required', function (): void {
    expect(RegistrationMode::Required->isRequired())->toBeTrue();
    expect(RegistrationMode::Optional->isRequired())->toBeFalse();
    expect(RegistrationMode::None->isRequired())->toBeFalse();
});

it('identifies open door', function (): void {
    expect(RegistrationMode::None->isOpenDoor())->toBeTrue();
    expect(RegistrationMode::Required->isOpenDoor())->toBeFalse();
    expect(RegistrationMode::Optional->isOpenDoor())->toBeFalse();
});

it('provides labels', function (): void {
    expect(RegistrationMode::Required->label())->toBe('Required');
    expect(RegistrationMode::Optional->label())->toBe('Optional');
    expect(RegistrationMode::None->label())->toBe('Open Door');
});

it('provides options array', function (): void {
    $options = RegistrationMode::options();
    expect($options)->toHaveCount(3);
    expect($options['required'])->toBe('Required');
    expect($options['optional'])->toBe('Optional');
    expect($options['none'])->toBe('Open Door');
});
