<?php

declare(strict_types=1);

use AIArmada\Chip\DataObjects\Client;

describe('Client data object', function (): void {
    it('creates a client from array data', function (): void {
        $data = [
            'id' => 'client_123',
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+60123456789',
            'address' => [
                'street1' => '123 Main Street',
                'city' => 'Kuala Lumpur',
                'state' => 'Selangor',
                'country' => 'MY',
            ],
            'identity_type' => 'nric',
            'identity_number' => '123456-78-9012',
            'date_of_birth' => '1990-01-01',
            'nationality' => 'MY',
        ];

        $client = Client::fromArray($data);

        expect($client->id)->toBe('client_123');
        expect($client->fullName)->toBe('John Doe');
        expect($client->email)->toBe('john@example.com');
        expect($client->phone)->toBe('+60123456789');
        expect($client->address)->toBe([
            'street1' => '123 Main Street',
            'city' => 'Kuala Lumpur',
            'state' => 'Selangor',
            'country' => 'MY',
        ]);
        expect($client->identityType)->toBe('nric');
        expect($client->identityNumber)->toBe('123456-78-9012');
    });

    it('handles minimal client data', function (): void {
        $client = Client::fromArray([
            'id' => 'client_123',
            'full_name' => 'John Doe',
        ]);

        expect($client->fullName)->toBe('John Doe');
        expect($client->email)->toBeNull();
        expect($client->phone)->toBeNull();
        expect($client->address)->toBeNull();
    });

    it('exposes helpers for timelines and company detection', function (): void {
        $client = Client::fromArray([
            'id' => 'client_company',
            'full_name' => 'ACME Operations',
            'created_on' => strtotime('2024-01-01T00:00:00Z'),
            'updated_on' => strtotime('2024-01-02T00:00:00Z'),
            'legal_name' => 'ACME Sdn Bhd',
            'registration_number' => '202201234567',
        ]);

        expect($client->getCreatedAt()->toDateString())->toBe('2024-01-01');
        expect($client->getUpdatedAt()->toDateString())->toBe('2024-01-02');
        expect($client->isCompany())->toBeTrue();
        expect(isset($client->legalName))->toBeTrue();
        expect($client->toArray())->toMatchArray([
            'id' => 'client_company',
            'legal_name' => 'ACME Sdn Bhd',
            'registration_number' => '202201234567',
        ]);
    });
});
