<?php

declare(strict_types=1);

use AIArmada\Jnt\Console\Commands\HealthCheckCommand;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

describe('HealthCheckCommand', function (): void {
    beforeEach(function (): void {
        // Set valid config by default
        Config::set('jnt.api_account', 'test_account');
        Config::set('jnt.private_key', '8e88c8477d4e4939859c560192fcafbc'); // Valid hex string
        Config::set('jnt.environment', 'testing');
        Config::set('jnt.base_urls', [
            'testing' => 'https://demoopenapi.jtexpress.my/webopenplatformapi',
            'production' => 'https://ylopenapi.jtexpress.my/webopenplatformapi',
        ]);
    });

    it('passes health check with valid configuration', function (): void {
        Http::fake([
            '*' => Http::response('OK', 200),
        ]);

        $this->artisan(HealthCheckCommand::class)
            ->expectsOutput('🔍 J&T Express API Health Check')
            ->expectsOutput('✅ All systems operational')
            ->assertExitCode(0);
    });

    it('fails when API account is missing', function (): void {
        Config::set('jnt.api_account', null);

        $this->artisan(HealthCheckCommand::class)
            ->expectsOutput('🔍 J&T Express API Health Check')
            ->expectsOutput('❌ Some systems are experiencing issues')
            ->assertExitCode(1);
    });

    it('fails when private key is missing', function (): void {
        Config::set('jnt.private_key', null);

        $this->artisan(HealthCheckCommand::class)
            ->expectsOutput('🔍 J&T Express API Health Check')
            ->expectsOutput('❌ Some systems are experiencing issues')
            ->assertExitCode(1);
    });

    it('fails when base URLs are missing', function (): void {
        Config::set('jnt.base_urls', []);

        $this->artisan(HealthCheckCommand::class)
            ->expectsOutput('🔍 J&T Express API Health Check')
            ->expectsOutput('❌ Some systems are experiencing issues')
            ->assertExitCode(1);
    });

    it('shows warning when API is unreachable but configuration is valid', function (): void {
        Http::fake([
            '*' => Http::response('Server Error', 500),
        ]);

        $this->artisan(HealthCheckCommand::class)
            ->expectsOutput('🔍 J&T Express API Health Check')
            ->expectsOutput('✅ All systems operational') // Config is valid, so overall health passes
            ->assertExitCode(0);
    });

    it('displays configuration details in verbose mode', function (): void {
        Http::fake([
            '*' => Http::response('OK', 200),
        ]);

        $this->artisan(HealthCheckCommand::class, ['--verbose' => true])
            ->expectsOutput('🔍 J&T Express API Health Check')
            ->expectsOutput('⚙️  Configuration Status')
            ->expectsOutput('   Environment: testing')
            ->expectsOutput('   API Account: Configured')
            ->expectsOutput('   Private Key: Configured')
            ->expectsOutput('✅ All systems operational')
            ->assertExitCode(0);
    });

    it('shows missing configuration in verbose mode', function (): void {
        Config::set('jnt.api_account', null);
        Config::set('jnt.private_key', null);

        $this->artisan(HealthCheckCommand::class, ['--verbose' => true])
            ->expectsOutput('🔍 J&T Express API Health Check')
            ->expectsOutput('⚙️  Configuration Status')
            ->expectsOutput('   API Account: Missing')
            ->expectsOutput('   Private Key: Missing')
            ->expectsOutput('❌ Some systems are experiencing issues')
            ->assertExitCode(1);
    });

    it('handles different environments', function (): void {
        Config::set('jnt.environment', 'production');

        Http::fake([
            '*' => Http::response('OK', 200),
        ]);

        $this->artisan(HealthCheckCommand::class)
            ->expectsOutput('🔍 J&T Express API Health Check')
            ->expectsOutput('   ❌ Health checks are disabled for production environment')
            ->assertExitCode(1);
    });
});
