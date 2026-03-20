<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Traits\ValidatesConfiguration;

it('skips validation during package discovery in production', function (): void {
    putenv('APP_ENV=production');
    $this->refreshApplication();

    $previousArgv = $_SERVER['argv'] ?? null;
    $_SERVER['argv'] = ['artisan', 'package:discover'];

    $validator = new class
    {
        use ValidatesConfiguration;

        /**
         * @param  array<string>  $requiredKeys
         */
        public function validate(string $configFile, array $requiredKeys): void
        {
            $this->validateConfiguration($configFile, $requiredKeys);
        }
    };

    expect(fn () => $validator->validate('chip', ['collect.api_key']))->not->toThrow(RuntimeException::class);

    if ($previousArgv === null) {
        unset($_SERVER['argv']);
    } else {
        $_SERVER['argv'] = $previousArgv;
    }

    putenv('APP_ENV');
    $this->refreshApplication();
});

it('still validates missing configuration outside package discovery in production', function (): void {
    $previousArgv = $_SERVER['argv'] ?? null;
    $_SERVER['argv'] = ['artisan', 'chip:health-check'];
    config()->set('chip.validate_config', true);

    $validator = new class
    {
        use ValidatesConfiguration;

        /**
         * @param  array<string>  $requiredKeys
         */
        public function validate(string $configFile, array $requiredKeys): void
        {
            $this->validateConfiguration($configFile, $requiredKeys);
        }
    };

    expect(fn () => $validator->validate('chip', ['unit_test_missing_key']))
        ->toThrow(RuntimeException::class, 'Required configuration key [chip.unit_test_missing_key] is not set.');

    if ($previousArgv === null) {
        unset($_SERVER['argv']);
    } else {
        $_SERVER['argv'] = $previousArgv;
    }

    config()->set('chip.validate_config', false);
});
