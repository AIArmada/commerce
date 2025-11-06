<?php

declare(strict_types=1);

use AIArmada\Jnt\Exceptions\JntApiException;

describe('JntApiException', function (): void {
    it('creates exception for order creation failure', function (): void {
        $exception = JntApiException::orderCreationFailed('Invalid address', ['error' => 'details']);

        expect($exception)
            ->toBeInstanceOf(JntApiException::class)
            ->getMessage()->toBe('Order creation failed: Invalid address')
            ->and($exception->errorCode)->toBe('ORDER_CREATE_ERROR')
            ->and($exception->endpoint)->toBe('order/addOrder')
            ->and($exception->apiResponse)->toBe(['error' => 'details']);
    });

    it('creates exception for order cancellation failure', function (): void {
        $exception = JntApiException::orderCancellationFailed('ORDER-123', 'Already delivered');

        expect($exception)
            ->toBeInstanceOf(JntApiException::class)
            ->getMessage()->toBe('Failed to cancel order ORDER-123: Already delivered')
            ->and($exception->errorCode)->toBe('ORDER_CANCEL_ERROR')
            ->and($exception->endpoint)->toBe('order/cancelOrder');
    });

    it('creates exception for tracking failure', function (): void {
        $exception = JntApiException::trackingFailed('ORDER-456', ['msg' => 'Not found']);

        expect($exception)
            ->toBeInstanceOf(JntApiException::class)
            ->getMessage()->toBe('Failed to retrieve tracking information for order ORDER-456')
            ->and($exception->errorCode)->toBe('TRACKING_ERROR')
            ->and($exception->endpoint)->toBe('logistics/trace')
            ->and($exception->apiResponse)->toBe(['msg' => 'Not found']);
    });

    it('creates exception for order query failure', function (): void {
        $exception = JntApiException::orderQueryFailed('ORDER-789');

        expect($exception)
            ->toBeInstanceOf(JntApiException::class)
            ->getMessage()->toBe('Failed to query order ORDER-789')
            ->and($exception->errorCode)->toBe('ORDER_QUERY_ERROR')
            ->and($exception->endpoint)->toBe('order/getOrders');
    });

    it('creates exception for print failure', function (): void {
        $exception = JntApiException::printFailed('ORDER-999', 'Template not found');

        expect($exception)
            ->toBeInstanceOf(JntApiException::class)
            ->getMessage()->toContain('Failed to print waybill for order ORDER-999')
            ->and($exception->errorCode)->toBe('PRINT_ERROR')
            ->and($exception->endpoint)->toBe('order/printOrder');
    });

    it('creates exception for invalid API response', function (): void {
        $response = ['unexpected' => 'format'];
        $exception = JntApiException::invalidApiResponse('order/addOrder', $response);

        expect($exception)
            ->toBeInstanceOf(JntApiException::class)
            ->getMessage()->toContain('Invalid API response format')
            ->and($exception->errorCode)->toBe('INVALID_RESPONSE')
            ->and($exception->endpoint)->toBe('order/addOrder')
            ->and($exception->apiResponse)->toBe($response);
    });

    it('creates exception for rate limit exceeded', function (): void {
        $exception = JntApiException::rateLimitExceeded('order/addOrder');

        expect($exception)
            ->toBeInstanceOf(JntApiException::class)
            ->getMessage()->toContain('Rate limit exceeded')
            ->and($exception->errorCode)->toBe('RATE_LIMIT_EXCEEDED')
            ->and($exception->endpoint)->toBe('order/addOrder');
    });

    it('creates exception for authentication failure', function (): void {
        $exception = JntApiException::authenticationFailed('Invalid API key');

        expect($exception)
            ->toBeInstanceOf(JntApiException::class)
            ->getMessage()->toBe('API authentication failed: Invalid API key')
            ->and($exception->errorCode)->toBe('AUTH_ERROR');
    });

    it('includes API response in exception data', function (): void {
        $response = [
            'code' => '0',
            'msg' => 'Error message',
            'data' => null,
        ];
        $exception = JntApiException::orderCreationFailed('API error', $response);

        expect($exception->apiResponse)->toBe($response);
    });

    it('allows null API response', function (): void {
        $exception = JntApiException::orderCreationFailed('Unknown error');

        expect($exception->apiResponse)->toBeNull();
    });
});
