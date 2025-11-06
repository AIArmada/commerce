<?php

declare(strict_types=1);

use AIArmada\Jnt\Exceptions\JntNetworkException;

describe('JntNetworkException', function (): void {
    it('creates exception for connection failure', function (): void {
        $previous = new Exception('Connection refused');
        $exception = JntNetworkException::connectionFailed('order/addOrder', $previous);

        expect($exception)
            ->toBeInstanceOf(JntNetworkException::class)
            ->getMessage()->toContain('Failed to connect to J&T API endpoint')
            ->and($exception->endpoint)->toBe('order/addOrder')
            ->and($exception->getPrevious())->toBe($previous);
    });

    it('creates exception for timeout', function (): void {
        $exception = JntNetworkException::timeout('logistics/trace', 30);

        expect($exception)
            ->toBeInstanceOf(JntNetworkException::class)
            ->getMessage()->toContain('timed out after 30 seconds')
            ->and($exception->endpoint)->toBe('logistics/trace');
    });

    it('creates exception for server error', function (): void {
        $exception = JntNetworkException::serverError('order/addOrder', 500, ['error' => 'Internal error']);

        expect($exception)
            ->toBeInstanceOf(JntNetworkException::class)
            ->getMessage()->toContain('server error (HTTP 500)')
            ->and($exception->endpoint)->toBe('order/addOrder')
            ->and($exception->httpStatus)->toBe(500);
    });

    it('creates exception for client error', function (): void {
        $exception = JntNetworkException::clientError('order/cancelOrder', 404);

        expect($exception)
            ->toBeInstanceOf(JntNetworkException::class)
            ->getMessage()->toContain('client error (HTTP 404)')
            ->and($exception->endpoint)->toBe('order/cancelOrder')
            ->and($exception->httpStatus)->toBe(404);
    });

    it('creates exception for DNS resolution failure', function (): void {
        $exception = JntNetworkException::dnsResolutionFailed('api.jnt.com');

        expect($exception)
            ->toBeInstanceOf(JntNetworkException::class)
            ->getMessage()->toContain('Failed to resolve DNS')
            ->and($exception->endpoint)->toBe('api.jnt.com');
    });

    it('creates exception for SSL error', function (): void {
        $exception = JntNetworkException::sslError('order/addOrder', 'Certificate verification failed');

        expect($exception)
            ->toBeInstanceOf(JntNetworkException::class)
            ->getMessage()->toContain('SSL/TLS error')
            ->getMessage()->toContain('Certificate verification failed')
            ->and($exception->endpoint)->toBe('order/addOrder');
    });

    it('creates exception for proxy error', function (): void {
        $exception = JntNetworkException::proxyError('logistics/trace', 'Proxy authentication required');

        expect($exception)
            ->toBeInstanceOf(JntNetworkException::class)
            ->getMessage()->toContain('Proxy error')
            ->and($exception->endpoint)->toBe('logistics/trace');
    });

    it('creates exception for too many redirects', function (): void {
        $exception = JntNetworkException::tooManyRedirects('order/printOrder', 10);

        expect($exception)
            ->toBeInstanceOf(JntNetworkException::class)
            ->getMessage()->toContain('Too many redirects (10)')
            ->and($exception->endpoint)->toBe('order/printOrder');
    });
});
