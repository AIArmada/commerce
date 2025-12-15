<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Console\SetupCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('SetupCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new SetupCommand;
        $signature = $command->getName();

        expect($signature)->toBe('authz:setup');
    });

    it('fails in production without force flag', function (): void {
        app()->detectEnvironment(fn () => 'production');

        $this->artisan('authz:setup')
            ->expectsOutputToContain('Cannot run setup in production')
            ->assertFailed();
    });

    it('runs minimal setup without prompts', function (): void {
        app()->detectEnvironment(fn () => 'testing');

        // Minimal setup avoids interactive prompts
        $this->artisan('authz:setup', [
            '--minimal' => true,
            '--skip-policies' => true,
            '--skip-permissions' => true,
        ])->assertSuccessful();
    });

    it('accepts panel option', function (): void {
        app()->detectEnvironment(fn () => 'testing');

        $this->artisan('authz:setup', [
            '--minimal' => true,
            '--panel' => 'admin',
            '--skip-policies' => true,
            '--skip-permissions' => true,
        ])->assertSuccessful();
    });

    it('accepts tenant option', function (): void {
        app()->detectEnvironment(fn () => 'testing');

        $this->artisan('authz:setup', [
            '--minimal' => true,
            '--tenant' => 'App\Models\Team',
            '--skip-policies' => true,
            '--skip-permissions' => true,
        ])->assertSuccessful();
    });

    it('displays welcome message', function (): void {
        app()->detectEnvironment(fn () => 'testing');

        $this->artisan('authz:setup', [
            '--minimal' => true,
            '--skip-policies' => true,
            '--skip-permissions' => true,
        ])
            ->expectsOutputToContain('Filament Authz Setup Wizard')
            ->assertSuccessful();
    });

    it('displays setup complete message', function (): void {
        app()->detectEnvironment(fn () => 'testing');

        $this->artisan('authz:setup', [
            '--minimal' => true,
            '--skip-policies' => true,
            '--skip-permissions' => true,
        ])
            ->expectsOutputToContain('Setup Complete')
            ->assertSuccessful();
    });

    it('creates super admin role', function (): void {
        app()->detectEnvironment(fn () => 'testing');

        $this->artisan('authz:setup', [
            '--minimal' => true,
            '--skip-policies' => true,
            '--skip-permissions' => true,
        ])->assertSuccessful();

        expect(Spatie\Permission\Models\Role::where('name', 'Super Admin')->exists())->toBeTrue();
    });

    it('shows next steps after completion', function (): void {
        app()->detectEnvironment(fn () => 'testing');

        $this->artisan('authz:setup', [
            '--minimal' => true,
            '--skip-policies' => true,
            '--skip-permissions' => true,
        ])
            ->expectsOutputToContain('Next steps')
            ->assertSuccessful();
    });
});
