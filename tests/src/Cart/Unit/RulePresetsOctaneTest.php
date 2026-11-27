<?php

declare(strict_types=1);

use AIArmada\Cart\CartServiceProvider;
use AIArmada\Cart\Services\BuiltInRulesFactory;
use AIArmada\Cart\Services\RulePresets;
use Illuminate\Queue\Events\Looping;

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

it('restores the RulePresets factory before a queue worker processes its next job', function (): void {
    $bootFactory = new BuiltInRulesFactory;
    RulePresets::setFactory($bootFactory);
    (new CartServiceProvider(app()))->bootingPackage();

    RulePresets::setFactory(null);
    event(new Looping('database', 'default'));

    expect((new ReflectionProperty(RulePresets::class, 'factory'))->getValue())->toBe($bootFactory);
});
