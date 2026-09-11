<?php

declare(strict_types=1);

use AIArmada\Cart\CartServiceProvider;
use AIArmada\Cart\Services\BuiltInRulesFactory;
use AIArmada\Cart\Services\RulePresets;

require_once __DIR__ . '/../../../Support/OctaneEventShims.php';

afterEach(function (): void {
    RulePresets::restoreOctaneDefaults();
    RulePresets::setFactory(null);
});

it('restores the RulePresets factory at a request boundary', function (): void {
    $bootFactory = new BuiltInRulesFactory;
    RulePresets::setFactory($bootFactory);

    $provider = new CartServiceProvider(app());
    $provider->bootingPackage();

    RulePresets::setFactory(null);

    $factory = new ReflectionProperty(RulePresets::class, 'factory');

    expect($factory->getValue())->toBeNull();

    $receivedEvent = 'Laravel\\Octane\\Events\\RequestReceived';
    event(new $receivedEvent);

    expect($factory->getValue())->toBe($bootFactory);
});
