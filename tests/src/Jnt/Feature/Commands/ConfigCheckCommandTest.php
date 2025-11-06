<?php

declare(strict_types=1);

use AIArmada\Jnt\Console\Commands\ConfigCheckCommand;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

describe('ConfigCheckCommand', function (): void {
    beforeEach(function (): void {
        // Set valid config by default
        Config::set('jnt.api_account', 'test_account');
        Config::set('jnt.private_key', '8e88c8477d4e4939859c560192fcafbc'); // Valid hex string like default
        Config::set('jnt.environment', 'testing');
        Config::set('jnt.base_urls', [
            'testing' => 'https://demoopenapi.jtexpress.my/webopenplatformapi',
            'production' => 'https://ylopenapi.jtexpress.my/webopenplatformapi',
        ]);
    });

    it('passes all checks with valid configuration', function (): void {
        Http::fake([
            '*' => Http::response('OK', 200),
        ]);

        $this->artisan(ConfigCheckCommand::class)
            ->expectsOutput('J&T Express Configuration Check')
            ->assertExitCode(0);
    });

    it('fails when API account is missing', function (): void {
        Config::set('jnt.api_account', null);

        $this->artisan(ConfigCheckCommand::class)
            ->expectsOutput('Configuration validation failed. Please fix the errors above.')
            ->assertExitCode(1);
    });

    it('fails when private key is missing', function (): void {
        Config::set('jnt.private_key', null);

        $this->artisan(ConfigCheckCommand::class)
            ->expectsOutput('Configuration validation failed. Please fix the errors above.')
            ->assertExitCode(1);
    });

    it('fails when private key has invalid format', function (): void {
        Config::set('jnt.private_key', 'invalid-key-format');

        $this->artisan(ConfigCheckCommand::class)
            ->expectsOutput('Configuration validation failed. Please fix the errors above.')
            ->assertExitCode(1);
    });

    it('fails when environment is invalid', function (): void {
        Config::set('jnt.environment', 'invalid');

        $this->artisan(ConfigCheckCommand::class)
            ->expectsOutput('Configuration validation failed. Please fix the errors above.')
            ->assertExitCode(1);
    });

    it('fails when base URLs are missing', function (): void {
        Config::set('jnt.base_urls', null);

        $this->artisan(ConfigCheckCommand::class)
            ->expectsOutput('Configuration validation failed. Please fix the errors above.')
            ->assertExitCode(1);
    });

    it('fails when base URLs are invalid', function (): void {
        Config::set('jnt.base_urls', [
            'testing' => 'not-a-valid-url',
            'production' => 'https://valid.url',
        ]);

        $this->artisan(ConfigCheckCommand::class)
            ->expectsOutput('Configuration validation failed. Please fix the errors above.')
            ->assertExitCode(1);
    });

    it('fails when connectivity test fails', function (): void {
        Http::fake([
            '*' => Http::response('Error', 500),
        ]);

        $this->artisan(ConfigCheckCommand::class)
            ->assertExitCode(1);
    });

    it('shows success message when all checks pass', function (): void {
        Http::fake([
            '*' => Http::response('OK', 200),
        ]);

        $this->artisan(ConfigCheckCommand::class)
            ->expectsOutput('✓ All checks passed! J&T Express is properly configured.')
            ->assertExitCode(0);
    });
});
