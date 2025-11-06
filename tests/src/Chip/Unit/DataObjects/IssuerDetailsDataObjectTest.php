<?php

declare(strict_types=1);

use AIArmada\Chip\DataObjects\IssuerDetails;

describe('IssuerDetails data object', function (): void {
    it('creates issuer details from array data', function (): void {
        $data = [
            'website' => 'https://chip.example',
            'legal_street_address' => '123 Test Street',
            'legal_country' => 'MY',
            'legal_city' => 'Kuala Lumpur',
            'legal_zip_code' => '50450',
            'legal_state' => 'KL',
            'bank_accounts' => [['id' => 1]],
            'legal_name' => 'Chip Example Sdn Bhd',
            'brand_name' => 'Chip Example',
            'registration_number' => '202201234567',
            'tax_number' => 'TX1234567',
        ];

        $details = IssuerDetails::fromArray($data);

        expect($details->legal_name)->toBe('Chip Example Sdn Bhd');
        expect($details->bank_accounts)->toHaveCount(1);
        expect($details->toArray())->toMatchArray($data);
    });

    it('defaults missing fields to sensible values', function (): void {
        $details = IssuerDetails::fromArray([]);

        expect($details->bank_accounts)->toBe([]);
        expect($details->toArray())->toMatchArray([
            'website' => null,
            'legal_street_address' => null,
            'legal_country' => null,
            'legal_city' => null,
            'legal_zip_code' => null,
            'legal_state' => null,
            'bank_accounts' => [],
            'legal_name' => null,
            'brand_name' => null,
            'registration_number' => null,
            'tax_number' => null,
        ]);
    });
});
