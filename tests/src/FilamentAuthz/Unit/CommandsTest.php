<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Console\Concerns\Prohibitable;
use AIArmada\FilamentAuthz\Console\DiscoverCommand;
use AIArmada\FilamentAuthz\Console\GeneratePoliciesCommand;
use AIArmada\FilamentAuthz\Console\SeederCommand;
use AIArmada\FilamentAuthz\Console\SuperAdminCommand;
use AIArmada\FilamentAuthz\Console\SyncAuthzCommand;

describe('GeneratePoliciesCommand', function () {
    it('exists', function () {
        expect(class_exists(GeneratePoliciesCommand::class))->toBeTrue();
    });

    it('uses prohibitable trait', function () {
        $traits = class_uses_recursive(GeneratePoliciesCommand::class);

        expect($traits)->toHaveKey(Prohibitable::class);
    });

    it('has correct command signature', function () {
        $command = app(GeneratePoliciesCommand::class);
        $reflection = new ReflectionClass($command);

        $property = $reflection->getProperty('signature');
        $property->setAccessible(true);

        expect($property->getValue($command))->toContain('authz:policies');
    });
});

describe('SeederCommand', function () {
    it('exists', function () {
        expect(class_exists(SeederCommand::class))->toBeTrue();
    });

    it('uses prohibitable trait', function () {
        $traits = class_uses_recursive(SeederCommand::class);

        expect($traits)->toHaveKey(Prohibitable::class);
    });

    it('has correct command signature', function () {
        $command = app(SeederCommand::class);
        $reflection = new ReflectionClass($command);

        $property = $reflection->getProperty('signature');
        $property->setAccessible(true);

        expect($property->getValue($command))->toContain('authz:seeder');
    });
});

describe('SuperAdminCommand', function () {
    it('exists', function () {
        expect(class_exists(SuperAdminCommand::class))->toBeTrue();
    });

    it('uses prohibitable trait', function () {
        $traits = class_uses_recursive(SuperAdminCommand::class);

        expect($traits)->toHaveKey(Prohibitable::class);
    });

    it('has correct command signature', function () {
        $command = app(SuperAdminCommand::class);
        $reflection = new ReflectionClass($command);

        $property = $reflection->getProperty('signature');
        $property->setAccessible(true);

        expect($property->getValue($command))->toContain('authz:super-admin');
    });
});

describe('SyncAuthzCommand', function () {
    it('exists', function () {
        expect(class_exists(SyncAuthzCommand::class))->toBeTrue();
    });

    it('uses prohibitable trait', function () {
        $traits = class_uses_recursive(SyncAuthzCommand::class);

        expect($traits)->toHaveKey(Prohibitable::class);
    });

    it('has correct command signature', function () {
        $command = app(SyncAuthzCommand::class);
        $reflection = new ReflectionClass($command);

        $property = $reflection->getProperty('signature');
        $property->setAccessible(true);

        expect($property->getValue($command))->toContain('authz:sync');
    });
});

describe('DiscoverCommand', function () {
    it('exists', function () {
        expect(class_exists(DiscoverCommand::class))->toBeTrue();
    });

    it('has correct command signature', function () {
        $command = app(DiscoverCommand::class);
        $reflection = new ReflectionClass($command);

        $property = $reflection->getProperty('signature');
        $property->setAccessible(true);

        expect($property->getValue($command))->toContain('authz:discover');
    });
});
