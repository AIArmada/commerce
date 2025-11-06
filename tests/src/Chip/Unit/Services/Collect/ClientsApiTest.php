<?php

declare(strict_types=1);

use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\DataObjects\Client;
use AIArmada\Chip\Services\Collect\ClientsApi;

beforeEach(function (): void {
    $this->client = Mockery::mock(ChipCollectClient::class);
    $this->api = new ClientsApi($this->client);
});

afterEach(function (): void {
    Mockery::close();
});

describe('Collect Clients API', function (): void {
    it('creates and retrieves clients', function (): void {
        $clientPayload = [
            'id' => 'client_123',
            'email' => 'buyer@example.com',
        ];

        $this->client->shouldReceive('post')
            ->once()
            ->with('clients/', ['email' => 'buyer@example.com'])
            ->andReturn($clientPayload);

        $created = $this->api->create(['email' => 'buyer@example.com']);

        expect($created)->toBeInstanceOf(Client::class);
        expect($created->id)->toBe('client_123');

        $this->client->shouldReceive('get')
            ->once()
            ->with('clients/client_123/')
            ->andReturn($clientPayload);

        $found = $this->api->find('client_123');

        expect($found->email)->toBe('buyer@example.com');
    });

    it('lists clients with filters', function (): void {
        $this->client->shouldReceive('get')
            ->once()
            ->with('clients/?status=active&page=2')
            ->andReturn(['results' => []]);

        expect($this->api->list(['status' => 'active', 'page' => 2]))
            ->toBe(['results' => []]);
    });

    it('updates and partially updates clients', function (): void {
        $updatedPayload = ['id' => 'client_123', 'email' => 'new@example.com'];

        $this->client->shouldReceive('put')
            ->once()
            ->with('clients/client_123/', ['email' => 'new@example.com'])
            ->andReturn($updatedPayload);

        $updated = $this->api->update('client_123', ['email' => 'new@example.com']);

        expect($updated)->toBeInstanceOf(Client::class);
        expect($updated->email)->toBe('new@example.com');

        $patchedPayload = ['id' => 'client_123', 'phone' => '+60123456789'];

        $this->client->shouldReceive('patch')
            ->once()
            ->with('clients/client_123/', ['phone' => '+60123456789'])
            ->andReturn($patchedPayload);

        $patched = $this->api->partialUpdate('client_123', ['phone' => '+60123456789']);

        expect($patched->phone)->toBe('+60123456789');
    });

    it('deletes clients and recurring tokens', function (): void {
        $this->client->shouldReceive('delete')
            ->once()
            ->with('clients/client_123/');

        expect(fn () => $this->api->delete('client_123'))->not->toThrow(Exception::class);

        $this->client->shouldReceive('delete')
            ->once()
            ->with('clients/client_123/recurring_tokens/token_456/');

        expect(fn () => $this->api->deleteRecurringToken('client_123', 'token_456'))
            ->not->toThrow(Exception::class);
    });

    it('manages client recurring tokens', function (): void {
        $this->client->shouldReceive('get')
            ->once()
            ->with('clients/client_123/recurring_tokens/')
            ->andReturn(['tokens' => []]);

        expect($this->api->recurringTokens('client_123'))->toBe(['tokens' => []]);

        $this->client->shouldReceive('get')
            ->once()
            ->with('clients/client_123/recurring_tokens/token_456/')
            ->andReturn(['id' => 'token_456']);

        expect($this->api->recurringToken('client_123', 'token_456'))
            ->toBe(['id' => 'token_456']);
    });
});
