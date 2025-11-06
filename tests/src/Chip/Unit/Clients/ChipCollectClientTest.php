<?php

declare(strict_types=1);

use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Exceptions\ChipApiException;
use Illuminate\Support\Facades\Http;

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
});

describe('ChipCollectClient Retry Logic', function (): void {
    it('retries on server errors and surfaces the exception', function (): void {
        Http::fake(['*' => Http::response(['error' => 'Server Error'], 500)]);

        expect(fn () => $this->client->get('/test'))
            ->toThrow(ChipApiException::class, 'Server Error');

        Http::assertSentCount(3);
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
