<?php

declare(strict_types=1);

use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Exceptions\ChipApiException;
use AIArmada\Chip\Exceptions\ChipRateLimitException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Monolog\Handler\TestHandler;
use Monolog\Logger;

function chipSkipWhenRfc850BoundaryUnreachable(): void
{
    $nowYear = (int) (new DateTimeImmutable('now', new DateTimeZone('GMT')))->format('Y');

    // The TRUE arm needs year(now+50y) below the next century; 2-digit
    // years cannot express it otherwise (first window 2050–2099).
    if ($nowYear + 50 >= intdiv($nowYear, 100) * 100 + 100) {
        test()->markTestSkipped('RFC 850 fifty-year boundary unreachable from this decade.');
    }
}

beforeEach(function (): void {
    $this->client = new ChipCollectClient(
        apiKey: 'test_api_key',
        brandId: 'test_brand_id',
        baseUrl: 'https://gate.chip-in.asia/api/v1/',
        timeout: 30,
        retryConfig: ['attempts' => 3, 'delay' => 1000]
    );
});

describe('ChipCollectClient Authentication', function (): void {
    it('adds bearer token to requests', function (): void {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->client->get('/test');

        Http::assertSent(function ($request) {
            return $request->header('Authorization')[0] === 'Bearer test_api_key';
        });
    });

    it('sets correct content type', function (): void {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->client->post('/test', ['key' => 'value']);

        Http::assertSent(function ($request) {
            return $request->header('Content-Type')[0] === 'application/json';
        });
    });

    it('does not add an undocumented idempotency header to mutations', function (): void {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->client->post('/test', ['key' => 'value']);

        Http::assertSent(fn ($request): bool => ! $request->hasHeader('Idempotency-Key'));
    });
});

describe('ChipCollectClient Request Methods', function (): void {
    it('can make GET requests', function (): void {
        Http::fake(['*' => Http::response(['data' => ['id' => '123']], 200)]);

        $response = $this->client->get('/test');

        expect($response)->toBe(['data' => ['id' => '123']]);
        Http::assertSent(fn ($request) => $request->method() === 'GET');
    });

    it('can make POST requests', function (): void {
        Http::fake(['*' => Http::response(['data' => ['created' => true]], 201)]);

        $response = $this->client->post('/test', ['name' => 'Test']);

        expect($response)->toBe(['data' => ['created' => true]]);
        Http::assertSent(fn ($request) => $request->method() === 'POST');
    });

    it('can make PUT requests', function (): void {
        Http::fake(['*' => Http::response(['data' => ['updated' => true]], 200)]);

        $response = $this->client->put('/test', ['name' => 'Updated']);

        expect($response)->toBe(['data' => ['updated' => true]]);
        Http::assertSent(fn ($request) => $request->method() === 'PUT');
    });

    it('can make DELETE requests', function (): void {
        Http::fake(['*' => Http::response([], 204)]);

        $response = $this->client->delete('/test');

        expect($response)->toBe([]);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE');
    });
});

describe('ChipCollectClient Error Handling', function (): void {
    it('throws ChipApiException on 400 error', function (): void {
        Http::fake(['*' => Http::response(['error' => 'Bad Request'], 400)]);

        expect(fn () => $this->client->get('/test'))
            ->toThrow(ChipApiException::class, 'Bad Request');
    });

    it('throws ChipApiException on 401 error', function (): void {
        Http::fake(['*' => Http::response(['error' => 'Unauthorized'], 401)]);

        expect(fn () => $this->client->get('/test'))
            ->toThrow(ChipApiException::class, 'Unauthorized');
    });

    it('throws ChipApiException on 404 error', function (): void {
        Http::fake(['*' => Http::response(['error' => 'Not Found'], 404)]);

        expect(fn () => $this->client->get('/test'))
            ->toThrow(ChipApiException::class, 'Not Found');
    });

    it('includes error details in exception', function (): void {
        Http::fake(['*' => Http::response([
            'error' => 'Validation failed',
            'details' => ['field' => 'required'],
        ], 422)]);

        try {
            $this->client->get('/test');
        } catch (ChipApiException $e) {
            expect($e->getMessage())->toBe('Validation failed');
            expect($e->getErrorDetails())->toBe([
                'error' => 'Validation failed',
                'details' => ['field' => 'required'],
            ]);
            expect($e->getStatusCode())->toBe(422);
        }
    });

    it('surfaces object-shaped __all__ errors with code', function (): void {
        Http::fake(['*' => Http::response([
            '__all__' => ['message' => 'descriptive error message', 'code' => 'error_code'],
        ], 400)]);

        try {
            $this->client->get('/test');
            $this->fail('Expected ChipApiException to be thrown');
        } catch (ChipApiException $e) {
            expect($e->getMessage())->toBe('descriptive error message');
            expect($e->getErrorCode())->toBe('error_code');
            expect($e->getErrorMessage())->toBe('descriptive error message');
        }
    });

    it('surfaces list-shaped __all__ errors with code', function (): void {
        Http::fake(['*' => Http::response([
            '__all__' => [
                ['message' => 'Invalid or inactive recurring token!', 'code' => 'invalid_recurring_token'],
            ],
        ], 400)]);

        try {
            $this->client->get('/test');
            $this->fail('Expected ChipApiException to be thrown');
        } catch (ChipApiException $e) {
            expect($e->getMessage())->toBe('Invalid or inactive recurring token!');
            expect($e->getErrorCode())->toBe('invalid_recurring_token');
            expect($e->getErrorMessage())->toBe('Invalid or inactive recurring token!');
        }
    });

    it('uses a scalar error body as the message instead of throwing a TypeError', function (): void {
        config(['chip.http.rate_limit.enabled' => false]);
        Http::fake(['*' => Http::response('"Too many requests"', 502)]);

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipApiException to be thrown');
        } catch (ChipApiException $e) {
            expect($e->getMessage())->toBe('Too many requests');
        }
    });

    it('falls back to an unknown error on a non-string scalar body', function (): void {
        config(['chip.http.rate_limit.enabled' => false]);
        Http::fake(['*' => Http::response('42', 502)]);

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipApiException to be thrown');
        } catch (ChipApiException $e) {
            expect($e->getMessage())->toBe('API request failed with status 502');
        }
    });

    it('maps a server 429 with Retry-After through post()', function (): void {
        config(['chip.http.rate_limit.enabled' => false]);
        Http::fake(['*' => Http::response(['error' => 'Too Many Requests'], 429, ['Retry-After' => '120'])]);

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipRateLimitException to be thrown');
        } catch (ChipRateLimitException $e) {
            expect($e->getRetryAfter())->toBe(120);
            expect($e)->not->toBeInstanceOf(ChipApiException::class);
        }
    });

    it('defaults retry-after to 60 without the header', function (): void {
        config(['chip.http.rate_limit.enabled' => false]);
        Http::fake(['*' => Http::response(['error' => 'Too Many Requests'], 429)]);

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipRateLimitException to be thrown');
        } catch (ChipRateLimitException $e) {
            expect($e->getRetryAfter())->toBe(60);
        }
    });

    it('parses an HTTP-date Retry-After header', function (): void {
        config(['chip.http.rate_limit.enabled' => false]);
        Http::fake(['*' => Http::response(
            ['error' => 'Too Many Requests'],
            429,
            ['Retry-After' => gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT']
        )]);

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipRateLimitException to be thrown');
        } catch (ChipRateLimitException $e) {
            expect($e->getRetryAfter())->toBeGreaterThanOrEqual(3590)
                ->and($e->getRetryAfter())->toBeLessThanOrEqual(3600);
        }
    });

    it('clamps a past HTTP-date Retry-After header to zero', function (): void {
        config(['chip.http.rate_limit.enabled' => false]);
        Http::fake(['*' => Http::response(
            ['error' => 'Too Many Requests'],
            429,
            ['Retry-After' => 'Wed, 01 Jan 2020 00:00:00 GMT']
        )]);

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipRateLimitException to be thrown');
        } catch (ChipRateLimitException $e) {
            expect($e->getRetryAfter())->toBe(0);
        }
    });

    it('parses an RFC 850 Retry-After date', function (): void {
        config(['chip.http.rate_limit.enabled' => false]);
        Http::fake(['*' => Http::response(
            ['error' => 'Too Many Requests'],
            429,
            ['Retry-After' => 'Sunday, 06-Nov-94 08:49:37 GMT']
        )]);

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipRateLimitException to be thrown');
        } catch (ChipRateLimitException $e) {
            expect($e->getRetryAfter())->toBe(0);
        }
    });

    it('keeps an RFC 850 two-digit year within fifty years in the future', function (): void {
        config(['chip.http.rate_limit.enabled' => false]);
        Http::fake(['*' => Http::response(
            ['error' => 'Too Many Requests'],
            429,
            ['Retry-After' => 'Sunday, 30-Jun-75 12:00:00 GMT']
        )]);

        $expected = (new DateTimeImmutable('2075-06-30 12:00:00 GMT'))->getTimestamp() - time();

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipRateLimitException to be thrown');
        } catch (ChipRateLimitException $e) {
            expect($e->getRetryAfter())->toBeGreaterThanOrEqual($expected - 10)
                ->and($e->getRetryAfter())->toBeLessThanOrEqual($expected);
        }
    });

    it('maps an RFC 850 date a day past fifty years ahead to the past century', function (): void {
        config(['chip.http.rate_limit.enabled' => false]);

        // Boundary-relative; skipped while 2-digit years cannot express
        // the boundary (first window 2050–2099).
        chipSkipWhenRfc850BoundaryUnreachable();

        $over = new DateTimeImmutable('now', new DateTimeZone('GMT'));
        $over = $over->modify('+50 years')->modify('+1 day');

        Http::fake(['*' => Http::response(
            ['error' => 'Too Many Requests'],
            429,
            ['Retry-After' => $over->format('l, d-M-y H:i:s') . ' GMT']
        )]);

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipRateLimitException to be thrown');
        } catch (ChipRateLimitException $e) {
            expect($e->getRetryAfter())->toBe(0);
        }
    });

    it('keeps an RFC 850 date a day short of fifty years ahead in the current century', function (): void {
        config(['chip.http.rate_limit.enabled' => false]);

        // Boundary-relative; skipped while 2-digit years cannot express
        // the boundary (first window 2050–2099).
        chipSkipWhenRfc850BoundaryUnreachable();

        $under = new DateTimeImmutable('now', new DateTimeZone('GMT'));
        $under = $under->modify('+50 years')->modify('-1 day');

        Http::fake(['*' => Http::response(
            ['error' => 'Too Many Requests'],
            429,
            ['Retry-After' => $under->format('l, d-M-y H:i:s') . ' GMT']
        )]);

        $expected = $under->getTimestamp() - time();

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipRateLimitException to be thrown');
        } catch (ChipRateLimitException $e) {
            expect($e->getRetryAfter())->toBeGreaterThanOrEqual($expected - 10)
                ->and($e->getRetryAfter())->toBeLessThanOrEqual($expected);
        }
    });

    it('rejects an IMF-fixdate Retry-After with a mismatched weekday', function (): void {
        config(['chip.http.rate_limit.enabled' => false]);
        Http::fake(['*' => Http::response(
            ['error' => 'Too Many Requests'],
            429,
            // June 30 2075 is a Sunday; Monday must not shift the date.
            ['Retry-After' => 'Mon, 30 Jun 2075 12:00:00 GMT']
        )]);

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipRateLimitException to be thrown');
        } catch (ChipRateLimitException $e) {
            expect($e->getRetryAfter())->toBe(60);
        }
    });

    it('rejects an RFC 850 Retry-After with a mismatched weekday', function (): void {
        config(['chip.http.rate_limit.enabled' => false]);
        Http::fake(['*' => Http::response(
            ['error' => 'Too Many Requests'],
            429,
            ['Retry-After' => 'Monday, 30-Jun-75 12:00:00 GMT']
        )]);

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipRateLimitException to be thrown');
        } catch (ChipRateLimitException $e) {
            expect($e->getRetryAfter())->toBe(60);
        }
    });

    it('rejects an asctime Retry-After with a mismatched weekday', function (): void {
        config(['chip.http.rate_limit.enabled' => false]);
        Http::fake(['*' => Http::response(
            ['error' => 'Too Many Requests'],
            429,
            ['Retry-After' => 'Mon Jun 30 12:00:00 2075']
        )]);

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipRateLimitException to be thrown');
        } catch (ChipRateLimitException $e) {
            expect($e->getRetryAfter())->toBe(60);
        }
    });

    it('falls back to 60 when the RFC 850 tentative date is unparseable', function (): void {
        config(['chip.http.rate_limit.enabled' => false]);
        Http::fake(['*' => Http::response(
            ['error' => 'Too Many Requests'],
            429,
            ['Retry-After' => 'Monday, 01-Zzz-76 12:00:00 GMT']
        )]);

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipRateLimitException to be thrown');
        } catch (ChipRateLimitException $e) {
            expect($e->getRetryAfter())->toBe(60);
        }
    });

    it('parses an ANSI C asctime Retry-After date', function (): void {
        config(['chip.http.rate_limit.enabled' => false]);
        Http::fake(['*' => Http::response(
            ['error' => 'Too Many Requests'],
            429,
            ['Retry-After' => 'Sun Nov  6 08:49:37 1994']
        )]);

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipRateLimitException to be thrown');
        } catch (ChipRateLimitException $e) {
            expect($e->getRetryAfter())->toBe(0);
        }
    });

    it('treats a relative Retry-After value as missing', function (): void {
        config(['chip.http.rate_limit.enabled' => false]);
        Http::fake(['*' => Http::response(
            ['error' => 'Too Many Requests'],
            429,
            ['Retry-After' => 'tomorrow']
        )]);

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipRateLimitException to be thrown');
        } catch (ChipRateLimitException $e) {
            expect($e->getRetryAfter())->toBe(60);
        }
    });
});

describe('ChipCollectClient Rate Limit Logging', function (): void {
    it('logs a single masked warning on a server 429', function (): void {
        config(['chip.http.rate_limit.enabled' => false, 'chip.logging.enabled' => true, 'chip.logging.log_requests' => false, 'chip.logging.log_responses' => false]);

        $handler = new TestHandler;
        Log::shouldReceive('channel')->andReturn(new Logger('test', [$handler]));

        Http::fake(['*' => Http::response(
            ['error' => 'Too Many Requests', 'email' => 'buyer@example.com'],
            429,
            ['Retry-After' => '120']
        )]);

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipRateLimitException to be thrown');
        } catch (ChipRateLimitException $e) {
            expect($e->getRetryAfter())->toBe(120);
        }

        $records = $handler->getRecords();

        expect($records)->toHaveCount(1)
            ->and($handler->hasWarning('CHIP API rate limited'))->toBeTrue()
            ->and($records[0]->context['retry_after'])->toBe(120)
            ->and($records[0]->context['source'])->toBe('server')
            ->and($records[0]->context['response_data']['email'])->toBe('***MASKED***')
            ->and($records[0]->context['response_data']['error'])->toBe('Too Many Requests');
    });

    it('logs an empty payload on a non-JSON 429 body', function (): void {
        config(['chip.http.rate_limit.enabled' => false, 'chip.logging.enabled' => true, 'chip.logging.log_requests' => false, 'chip.logging.log_responses' => false]);

        $handler = new TestHandler;
        Log::shouldReceive('channel')->andReturn(new Logger('test', [$handler]));

        Http::fake(['*' => Http::response('<html>proxy error</html>', 429, ['Retry-After' => '30'])]);

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipRateLimitException to be thrown');
        } catch (ChipRateLimitException $e) {
            expect($e->getRetryAfter())->toBe(30);
        }

        $records = $handler->getRecords();

        expect($records)->toHaveCount(1)
            ->and($records[0]->context['response_data'])->toBe([]);
    });

    it('logs a local warning when the client rate limiter trips', function (): void {
        config(['chip.logging.enabled' => true, 'chip.logging.log_requests' => false, 'chip.logging.log_responses' => false]);

        $handler = new TestHandler;
        Log::shouldReceive('channel')->andReturn(new Logger('test', [$handler]));
        RateLimiter::shouldReceive('tooManyAttempts')->once()->andReturn(true);
        RateLimiter::shouldReceive('availableIn')->once()->andReturn(45);

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipRateLimitException to be thrown');
        } catch (ChipRateLimitException $e) {
            expect($e->getRetryAfter())->toBe(45);
        }

        $records = $handler->getRecords();

        expect($records)->toHaveCount(1)
            ->and($handler->hasWarning('CHIP API rate limited'))->toBeTrue()
            ->and($records[0]->context['source'])->toBe('local')
            ->and($records[0]->context['retry_after'])->toBe(45);
    });

    it('logs response context at warning with one authoritative error line', function (): void {
        config(['chip.http.rate_limit.enabled' => false, 'chip.logging.enabled' => true, 'chip.logging.log_requests' => false, 'chip.logging.log_responses' => false]);

        $handler = new TestHandler;
        Log::shouldReceive('channel')->andReturn(new Logger('test', [$handler]));

        Http::fake(['*' => Http::response(['error' => 'Bad Request'], 400)]);

        try {
            $this->client->post('/test', ['key' => 'value']);
            $this->fail('Expected ChipApiException to be thrown');
        } catch (ChipApiException $e) {
            expect($e->getMessage())->toBe('Bad Request');
        }

        $records = $handler->getRecords();

        expect($records)->toHaveCount(2)
            ->and($handler->hasWarning('CHIP API Error Response'))->toBeTrue()
            ->and($handler->hasError('CHIP API Request Failed'))->toBeTrue()
            ->and($records[0]->context['status'])->toBe(400);
    });
});

describe('ChipCollectClient Retry Logic', function (): void {
    it('retries on server errors and surfaces the exception', function (): void {
        Http::fake(['*' => Http::response(['error' => 'Server Error'], 500)]);

        expect(fn () => $this->client->get('/test'))
            ->toThrow(ChipApiException::class, 'Server Error');

        Http::assertSentCount(3);
    });

    it('does not retry mutation requests without documented provider idempotency', function (): void {
        Http::fake(['*' => Http::response(['error' => 'Server Error'], 500)]);

        expect(fn () => $this->client->post('/test'))
            ->toThrow(ChipApiException::class, 'Server Error');

        Http::assertSentCount(1);
    });
});

describe('ChipCollectClient Configuration', function (): void {
    it('uses configured base URL', function (): void {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->client->get('/test');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'gate.chip-in.asia');
        });
    });
});
