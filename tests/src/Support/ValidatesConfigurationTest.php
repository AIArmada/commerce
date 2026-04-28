<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Traits\ValidatesConfiguration;

it('skips validation in production console when validate_config is disabled', function (): void {
    putenv('APP_ENV=production');
    $this->refreshApplication();

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

    putenv('APP_ENV');
    $this->refreshApplication();
});

it('validates missing configuration in production console when validate_config is enabled', function (): void {
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

    config()->set('chip.validate_config', false);
});
