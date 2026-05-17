<?php

declare(strict_types=1);

use AIArmada\Growth\GrowthServiceProvider;
use Illuminate\Support\Facades\Blade;

it('fails fast when blade directives are enabled and variant directive is already defined', function (): void {
    config()->set('growth.features.blade_directives.enabled', true);

    Blade::directive('variant', static function (string $expression): string {
        return "<?php if ({$expression}): ?>";
    });

    /** @var GrowthServiceProvider $provider */
    $provider = app()->getProvider(GrowthServiceProvider::class);
    expect($provider)->toBeInstanceOf(GrowthServiceProvider::class);

    $method = new ReflectionMethod($provider, 'registerBladeDirectives');
    $method->setAccessible(true);

    expect(fn (): mixed => $method->invoke($provider))
        ->toThrow(InvalidArgumentException::class, 'Growth Blade directives cannot be registered because these directive names are already defined: variant');
});
