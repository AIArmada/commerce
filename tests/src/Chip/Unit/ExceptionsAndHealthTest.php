<?php

declare(strict_types=1);

use AIArmada\Chip\Exceptions\NoRecurringTokenException;
use AIArmada\Chip\Health\ChipGatewayCheck;
use Illuminate\Support\Facades\Http;
use Spatie\Health\Checks\Result;

describe('NoRecurringTokenException', function () {
    it('can be constructed with default message', function () {
        $exception = new NoRecurringTokenException;

        expect($exception)->toBeInstanceOf(NoRecurringTokenException::class)
            ->and($exception->getMessage())->toBe('No recurring token available');
    });

    it('can be constructed with custom message', function () {
        $exception = new NoRecurringTokenException('Custom error message');

        expect($exception->getMessage())->toBe('Custom error message');
    });

    it('is throwable', function () {
        expect(fn() => throw new NoRecurringTokenException('Test'))
            ->toThrow(NoRecurringTokenException::class, 'Test');
    });
});

describe('ChipGatewayCheck', function () {
    it('can be instantiated', function () {
        $check = new ChipGatewayCheck;

        expect($check)->toBeInstanceOf(ChipGatewayCheck::class)
            ->and($check->name)->toBe('CHIP Payment Gateway');
    });

    it('can set endpoint', function () {
        $check = new ChipGatewayCheck;
        $result = $check->endpoint('https://custom-endpoint.com/');

        expect($result)->toBe($check); // Returns self for chaining
    });

    it('can set timeout', function () {
        $check = new ChipGatewayCheck;
        $result = $check->timeout(30);

        expect($result)->toBe($check); // Returns self for chaining
    });

    it('returns warning when credentials not configured', function () {
        config(['chip.brand_id' => null, 'chip.api_key' => null]);

        $check = new ChipGatewayCheck;
        $result = $check->run();

        expect($result)->toBeInstanceOf(Result::class)
            ->and($result->status->value)->toBe('warning');
    });

    it('returns warning when only brand_id is missing', function () {
        config(['chip.brand_id' => null, 'chip.api_key' => 'test-key']);

        $check = new ChipGatewayCheck;
        $result = $check->run();

        expect($result)->toBeInstanceOf(Result::class)
            ->and($result->status->value)->toBe('warning');
    });

    it('returns warning when only api_key is missing', function () {
        config(['chip.brand_id' => 'test-brand', 'chip.api_key' => null]);

        $check = new ChipGatewayCheck;
        $result = $check->run();

        expect($result)->toBeInstanceOf(Result::class)
            ->and($result->status->value)->toBe('warning');
    });

    it('returns success when API responds successfully', function () {
        config([
            'chip.brand_id' => 'test-brand-123',
            'chip.api_key' => 'test-api-key',
        ]);

        Http::fake([
            '*' => Http::response(['id' => 'test-brand-123', 'name' => 'Test Brand'], 200),
        ]);

        $check = new ChipGatewayCheck;
        $result = $check->run();

        expect($result)->toBeInstanceOf(Result::class)
            ->and($result->status->value)->toBe('ok');
    });

    it('returns failure when API responds with error', function () {
        config([
            'chip.brand_id' => 'test-brand-123',
            'chip.api_key' => 'test-api-key',
        ]);

        Http::fake([
            '*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $check = new ChipGatewayCheck;
        $result = $check->run();

        expect($result)->toBeInstanceOf(Result::class)
            ->and($result->status->value)->toBe('failed');
    });

    it('returns failure when connection fails', function () {
        config([
            'chip.brand_id' => 'test-brand-123',
            'chip.api_key' => 'test-api-key',
        ]);

        Http::fake(function () {
            throw new \Exception('Connection refused');
        });

        $check = new ChipGatewayCheck;
        $result = $check->run();

        expect($result)->toBeInstanceOf(Result::class)
            ->and($result->status->value)->toBe('failed');
    });
});
