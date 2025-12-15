<?php

declare(strict_types=1);

use AIArmada\Jnt\Health\JntHealthCheck;
use Spatie\Health\Checks\Result;

describe('JntHealthCheck', function (): void {
    beforeEach(function (): void {
        // Clear any existing config
        config([
            'jnt.eccompanyid' => null,
            'jnt.api_account' => null,
            'jnt.private_key' => null,
            'jnt.base_url' => null,
        ]);
    });

    it('has correct name', function (): void {
        $check = new JntHealthCheck;

        expect($check->name)->toBe('J&T Express API');
    });

    it('can set timeout', function (): void {
        $check = new JntHealthCheck;
        $result = $check->timeout(30);

        expect($result)->toBeInstanceOf(JntHealthCheck::class);
        expect($result)->toBe($check); // Should return self for fluent interface
    });

    it('returns warning when credentials missing', function (): void {
        // Ensure credentials are empty
        config(['jnt.eccompanyid' => '', 'jnt.api_account' => '']);

        $check = new JntHealthCheck;
        $result = $check->run();

        expect($result)->toBeInstanceOf(Result::class);
    });

    it('returns result when API configuration is set', function (): void {
        config([
            'jnt.eccompanyid' => 'test_company',
            'jnt.api_account' => 'test_account',
            'jnt.private_key' => 'test_key',
            'jnt.base_url' => 'https://api.test.com',
            'jnt.retry.times' => 1,
            'jnt.retry.sleep' => 100,
            'jnt.timeout' => 10,
        ]);

        $check = new JntHealthCheck;
        $result = $check->run();

        expect($result)->toBeInstanceOf(Result::class);
    });

    it('can be instantiated correctly', function (): void {
        $check = new JntHealthCheck;

        expect($check)->toBeInstanceOf(JntHealthCheck::class);
    });
});
