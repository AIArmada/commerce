<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Console\Concerns\Prohibitable;
use AIArmada\FilamentAuthz\Console\DiscoverCommand;
use AIArmada\FilamentAuthz\Console\GeneratePoliciesCommand;
use AIArmada\FilamentAuthz\Console\SeederCommand;
use AIArmada\FilamentAuthz\Console\SuperAdminCommand;
use AIArmada\FilamentAuthz\Console\SyncAuthzCommand;

describe('GeneratePoliciesCommand', function (): void {
    it('exists', function (): void {
        expect(class_exists(GeneratePoliciesCommand::class))->toBeTrue();
    });

    it('uses prohibitable trait', function (): void {
        $traits = class_uses_recursive(GeneratePoliciesCommand::class);

        expect($traits)->toHaveKey(Prohibitable::class);
    });

    it('has correct command signature', function (): void {
        $command = app(GeneratePoliciesCommand::class);
        $reflection = new ReflectionClass($command);

        $property = $reflection->getProperty('signature');

        expect($property->getValue($command))->toContain('authz:policies');
    });
});

describe('SeederCommand', function (): void {
    it('exists', function (): void {
        expect(class_exists(SeederCommand::class))->toBeTrue();
    });

    it('uses prohibitable trait', function (): void {
        $traits = class_uses_recursive(SeederCommand::class);

        expect($traits)->toHaveKey(Prohibitable::class);
    });

    it('has correct command signature', function (): void {
        $command = app(SeederCommand::class);
        $reflection = new ReflectionClass($command);

        $property = $reflection->getProperty('signature');

        expect($property->getValue($command))->toContain('authz:seeder');
    });
});

describe('SuperAdminCommand', function (): void {
    it('exists', function (): void {
        expect(class_exists(SuperAdminCommand::class))->toBeTrue();
    });

    it('uses prohibitable trait', function (): void {
        $traits = class_uses_recursive(SuperAdminCommand::class);

        expect($traits)->toHaveKey(Prohibitable::class);
    });

    it('has correct command signature', function (): void {
        $command = app(SuperAdminCommand::class);
        $reflection = new ReflectionClass($command);

        $property = $reflection->getProperty('signature');

        expect($property->getValue($command))->toContain('authz:super-admin');
    });
});

describe('SyncAuthzCommand', function (): void {
    it('exists', function (): void {
        expect(class_exists(SyncAuthzCommand::class))->toBeTrue();
    });

    it('uses prohibitable trait', function (): void {
        $traits = class_uses_recursive(SyncAuthzCommand::class);

        expect($traits)->toHaveKey(Prohibitable::class);
    });

    it('has correct command signature', function (): void {
        $command = app(SyncAuthzCommand::class);
        $reflection = new ReflectionClass($command);

        $property = $reflection->getProperty('signature');

        expect($property->getValue($command))->toContain('authz:sync');
    });
});

describe('DiscoverCommand', function (): void {
    it('exists', function (): void {
        expect(class_exists(DiscoverCommand::class))->toBeTrue();
    });

    it('has correct command signature', function (): void {
        $command = app(DiscoverCommand::class);
        $reflection = new ReflectionClass($command);

        $property = $reflection->getProperty('signature');

        expect($property->getValue($command))->toContain('authz:discover');
    });
});
