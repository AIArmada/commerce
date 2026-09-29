<?php

declare(strict_types=1);

use AIArmada\Chip\Clients\ChipSendClient;
use AIArmada\Chip\Exceptions\ChipApiException;
use AIArmada\Chip\Exceptions\ChipRateLimitException;
use AIArmada\Chip\Exceptions\ChipValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Logger;

beforeEach(function (): void {
    $this->client = new ChipSendClient('test_api_key', 'test_secret_key', 'sandbox');
});

describe('ChipSendClient Authentication', function (): void {
    it('adds correct headers to requests', function (): void {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->client->get('/test');

        Http::assertSent(function ($request) {
            $headers = $request->headers();

            return isset($headers['epoch'][0]) &&
                   isset($headers['checksum'][0]) &&
                   isset($headers['Authorization'][0]) &&
                   $headers['Content-Type'][0] === 'application/json';
        });
    });

    it('generates valid HMAC signature', function (): void {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->client->post('/test', ['key' => 'value']);

        Http::assertSent(function ($request) {
            $epoch = $request->header('epoch')[0];
            $checksum = $request->header('checksum')[0];

            // CHIP Send signs the epoch followed immediately by the API key.
            $expectedChecksum = hash_hmac('sha512', $epoch . 'test_api_key', 'test_secret_key');

            return $checksum === $expectedChecksum;
        });
    });

});

describe('ChipSendClient Request Methods', function (): void {
    it('can make GET requests', function (): void {
        Http::fake(['*' => Http::response(['data' => ['id' => '123']], 200)]);

        $response = $this->client->get('/test');

        expect($response)->toBe(['data' => ['id' => '123']]);
        Http::assertSent(fn ($request) => $request->method() === 'GET');
    });

    it('can make POST requests with JSON body', function (): void {
        Http::fake(['*' => Http::response(['data' => ['created' => true]], 201)]);

        $response = $this->client->post('/test', ['name' => 'Test']);

        expect($response)->toBe(['data' => ['created' => true]]);
        Http::assertSent(function ($request) {
            return $request->method() === 'POST' &&
                   json_decode($request->body(), true) === ['name' => 'Test'];
        });
    });
});

describe('ChipSendClient Error Handling', function (): void {
    it('throws ChipValidationException with proper error details', function (): void {
        Http::fake(['*' => Http::response([
            'error' => 'Invalid amount',
            'code' => 'INVALID_AMOUNT',
        ], 400)]);

        try {
            $this->client->get('/test');
            $this->fail('Expected ChipValidationException to be thrown');
        } catch (ChipValidationException $e) {
            expect($e->getMessage())->toBe('Invalid amount');
            expect($e->getStatusCode())->toBe(400);
            expect($e->getErrorDetails())->toBe([
                'validation_errors' => [
                    'error' => 'Invalid amount',
                    'code' => 'INVALID_AMOUNT',
                ],
            ]);
        }
    });

    it('uses __all__ message for 400 validation errors', function (): void {
        Http::fake(['*' => Http::response([
            '__all__' => ['message' => 'descriptive error message', 'code' => 'error_code'],
        ], 400)]);

        try {
            $this->client->get('/test');
            $this->fail('Expected ChipValidationException to be thrown');
        } catch (ChipValidationException $e) {
            expect($e->getMessage())->toBe('descriptive error message');
            expect($e->getStatusCode())->toBe(400);
        }
    });

    it('surfaces __all__ code on non-400 errors', function (): void {
        Http::fake(['*' => Http::response([
            '__all__' => [
                ['message' => 'Something failed', 'code' => 'send_failed'],
            ],
        ], 402)]);

        try {
            $this->client->get('/test');
            $this->fail('Expected ChipApiException to be thrown');
        } catch (ChipApiException $e) {
            expect($e->getMessage())->toBe('Something failed');
            expect($e->getErrorCode())->toBe('send_failed');
        }
    });

    it('maps a server 429 with Retry-After through post()', function (): void {
        config(['chip.http.rate_limit.enabled' => false]);
        Http::fake(['*' => Http::response(['error' => 'Too Many Requests'], 429, ['Retry-After' => '30'])]);

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipRateLimitException to be thrown');
        } catch (ChipRateLimitException $e) {
            expect($e->getRetryAfter())->toBe(30);
        }
    });

    it('handles network timeouts', function (): void {
        Http::fake(['*' => Http::response(null, 408)]);

        expect(fn () => $this->client->get('/test'))
            ->toThrow(ChipApiException::class);
    });

    it('logs a single masked warning on a server 429', function (): void {
        config(['chip.http.rate_limit.enabled' => false, 'chip.logging.enabled' => true, 'chip.logging.log_requests' => false, 'chip.logging.log_responses' => false]);

        $handler = new TestHandler;
        Log::shouldReceive('channel')->andReturn(new Logger('test', [$handler]));

        Http::fake(['*' => Http::response(
            ['error' => 'Too Many Requests', 'account_number' => '1234567890'],
            429,
            ['Retry-After' => '30']
        )]);

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipRateLimitException to be thrown');
        } catch (ChipRateLimitException $e) {
            expect($e->getRetryAfter())->toBe(30);
        }

        $records = $handler->getRecords();

        expect($records)->toHaveCount(1)
            ->and($handler->hasWarning('CHIP API rate limited'))->toBeTrue()
            ->and($handler->hasErrorRecords())->toBeFalse()
            ->and($records[0]->context['retry_after'])->toBe(30)
            ->and($records[0]->context['source'])->toBe('server')
            ->and($records[0]->context['response_data']['account_number'])->toBe('***MASKED***');
    });

    it('keeps a single authoritative error line on non-rate-limit failures', function (): void {
        config(['chip.http.rate_limit.enabled' => false, 'chip.logging.enabled' => true, 'chip.logging.log_requests' => false, 'chip.logging.log_responses' => false]);

        $handler = new TestHandler;
        Log::shouldReceive('channel')->andReturn(new Logger('test', [$handler]));

        Http::fake(['*' => Http::response(['error' => 'Unprocessable'], 422)]);

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipApiException to be thrown');
        } catch (ChipApiException $e) {
            expect($e->getMessage())->toBe('Unprocessable');
        }

        $records = $handler->getRecords();

        expect($records)->toHaveCount(1)
            ->and($handler->hasError('CHIP API Request Failed'))->toBeTrue()
            ->and($handler->hasWarningRecords())->toBeFalse();
    });
});

describe('ChipSendClient URL Building', function (): void {
    it('uses sandbox URL in test mode', function (): void {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->client->get('/test');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'staging-api.chip-in.asia');
        });
    });

    it('uses production URL in live mode', function (): void {
        $liveClient = new ChipSendClient('live_api_key', 'live_secret_key', 'production');
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $liveClient->get('/test');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.chip-in.asia');
        });
    });
});

describe('ChipSendClient Timestamp Generation', function (): void {
    it('generates current epoch timestamp', function (): void {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $beforeTime = time();
        $this->client->get('/test');
        $afterTime = time();

        Http::assertSent(function ($request) use ($beforeTime, $afterTime) {
            $timestamp = (int) $request->header('epoch')[0];

            return $timestamp >= $beforeTime && $timestamp <= $afterTime;
        });
    });
});
