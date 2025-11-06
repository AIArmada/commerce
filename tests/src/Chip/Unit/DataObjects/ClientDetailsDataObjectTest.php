<?php

declare(strict_types=1);

use AIArmada\Chip\DataObjects\ClientDetails;

describe('ClientDetails data object', function (): void {
    it('creates client details from array data', function (): void {
        $data = [
            'bank_account' => '1234567890',
            'bank_code' => 'MBBEMYKL',
            'email' => 'customer@example.com',
            'phone' => '+60123456789',
            'full_name' => 'Jane Customer',
            'personal_code' => '123456-78-9012',
            'street_address' => '123, Jalan Test',
            'country' => 'MY',
            'city' => 'Kuala Lumpur',
            'zip_code' => '50450',
            'state' => 'KL',
            'shipping_street_address' => 'Warehouse 1',
            'shipping_country' => 'MY',
            'shipping_city' => 'Kuala Lumpur',
            'shipping_zip_code' => '50450',
            'shipping_state' => 'KL',
            'cc' => ['finance@example.com'],
            'bcc' => ['audit@example.com'],
            'legal_name' => 'Example Sdn Bhd',
            'brand_name' => 'Example',
            'registration_number' => '202201234567',
            'tax_number' => 'TX1234567',
        ];

        $details = ClientDetails::fromArray($data);

        expect($details->email)->toBe('customer@example.com');
        expect($details->shipping_city)->toBe('Kuala Lumpur');
        expect($details->toArray())->toMatchArray($data);
    });

    it('uses defaults for missing optional fields', function (): void {
        $details = ClientDetails::fromArray([]);

        expect($details->email)->toBeNull();
        expect($details->cc)->toBe([]);
        expect($details->bank_account)->toBeNull();
        expect($details->bank_code)->toBeNull();
        expect($details->phone)->toBeNull();
        expect($details->full_name)->toBeNull();
        expect($details->personal_code)->toBeNull();
        expect($details->street_address)->toBeNull();
        expect($details->country)->toBeNull();
        expect($details->city)->toBeNull();
        expect($details->zip_code)->toBeNull();
        expect($details->state)->toBeNull();
        expect($details->shipping_street_address)->toBeNull();
        expect($details->shipping_country)->toBeNull();
        expect($details->shipping_city)->toBeNull();
        expect($details->shipping_zip_code)->toBeNull();
        expect($details->shipping_state)->toBeNull();
        expect($details->bcc)->toBe([]);
        expect($details->legal_name)->toBeNull();
        expect($details->brand_name)->toBeNull();
        expect($details->registration_number)->toBeNull();
        expect($details->tax_number)->toBeNull();
    });
});
