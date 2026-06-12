<?php

declare(strict_types=1);

use AIArmada\Contacting\Concerns\HasContactMethods;
use AIArmada\Contacting\Concerns\HasSocialProfiles;

test('traits are usable', function () {
    expect(trait_exists(HasContactMethods::class))->toBeTrue();
    expect(trait_exists(HasSocialProfiles::class))->toBeTrue();
});

test('HasContactMethods trait defines expected methods', function () {
    $methods = get_class_methods(new class
    {
        use HasContactMethods;
    });

    expect(in_array('contactMethods', $methods))->toBeTrue();
    expect(in_array('publicContactMethods', $methods))->toBeTrue();
    expect(in_array('primaryContactMethod', $methods))->toBeTrue();
    expect(in_array('contactMethodsOfType', $methods))->toBeTrue();
    expect(in_array('contactMethodsForPurpose', $methods))->toBeTrue();
    expect(in_array('addContactMethod', $methods))->toBeTrue();
});

test('HasSocialProfiles trait defines expected methods', function () {
    $methods = get_class_methods(new class
    {
        use HasSocialProfiles;
    });

    expect(in_array('socialProfiles', $methods))->toBeTrue();
    expect(in_array('publicSocialProfiles', $methods))->toBeTrue();
    expect(in_array('primarySocialProfile', $methods))->toBeTrue();
    expect(in_array('socialProfilesForPlatform', $methods))->toBeTrue();
    expect(in_array('addSocialProfile', $methods))->toBeTrue();
});
