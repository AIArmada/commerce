<?php

declare(strict_types=1);

use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Services\Collect\AccountApi;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    $this->client = Mockery::mock(ChipCollectClient::class);
    $this->api = new AccountApi($this->client);
});

describe('Collect Account API', function (): void {
    it('retrieves account balance', function (): void {
        $expected = ['balance' => 1000];

        $this->client->shouldReceive('get')
            ->once()
            ->with('account/balance/')
            ->andReturn($expected);

        $balance = $this->api->balance();

        expect($balance)->toBe($expected);
    });

    it('fetches turnover with filters', function (): void {
        $filters = ['date_from' => '2024-01-01'];
        $response = ['turnover' => 5000];

        $this->client->shouldReceive('get')
            ->once()
            ->with('account/turnover/?date_from=2024-01-01')
            ->andReturn($response);

        expect($this->api->turnover($filters))->toBe($response);
    });

    it('logs errors when account requests fail', function (): void {
        Log::spy();

        $this->client->shouldReceive('get')
            ->once()
            ->with('account/balance/')
            ->andThrow(new RuntimeException('connection failed'));

        expect(fn () => $this->api->balance())->toThrow(RuntimeException::class, 'connection failed');

        Log::shouldHaveLogged('error', function ($message, $context) {
            return str_contains($message, 'Failed to get CHIP account balance')
                && $context['error'] === 'connection failed';
        });
    });

    it('retrieves and cancels company statements', function (): void {
        $this->client->shouldReceive('get')
            ->once()
            ->with('company_statements/?status=active')
            ->andReturn(['data' => []]);

        expect($this->api->companyStatements(['status' => 'active']))->toBe(['data' => []]);

        $this->client->shouldReceive('get')
            ->once()
            ->with('company_statements/statement_123/')
            ->andReturn(['id' => 'statement_123']);

        expect($this->api->companyStatement('statement_123'))->toBe(['id' => 'statement_123']);

        $this->client->shouldReceive('post')
            ->once()
            ->with('company_statements/statement_123/cancel/')
            ->andReturn(['id' => 'statement_123', 'status' => 'cancelled']);

        expect($this->api->cancelCompanyStatement('statement_123'))
            ->toBe(['id' => 'statement_123', 'status' => 'cancelled']);
    });
});
