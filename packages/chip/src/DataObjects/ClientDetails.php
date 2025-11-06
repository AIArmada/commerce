<?php

declare(strict_types=1);

namespace AIArmada\Chip\DataObjects;

final class ClientDetails
{
    /**
     * @param  array<int, mixed>  $cc
     * @param  array<int, mixed>  $bcc
     */
    public function __construct(
        public readonly ?string $bank_account,
        public readonly ?string $bank_code,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $full_name,
        public readonly ?string $personal_code,
        public readonly ?string $street_address,
        public readonly ?string $country,
        public readonly ?string $city,
        public readonly ?string $zip_code,
        public readonly ?string $state,
        public readonly ?string $shipping_street_address,
        public readonly ?string $shipping_country,
        public readonly ?string $shipping_city,
        public readonly ?string $shipping_zip_code,
        public readonly ?string $shipping_state,
        public readonly array $cc,
        public readonly array $bcc,
        public readonly ?string $legal_name,
        public readonly ?string $brand_name,
        public readonly ?string $registration_number,
        public readonly ?string $tax_number,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            bank_account: $data['bank_account'] ?? null,
            bank_code: $data['bank_code'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            full_name: $data['full_name'] ?? null,
            personal_code: $data['personal_code'] ?? null,
            street_address: $data['street_address'] ?? null,
            country: $data['country'] ?? null,
            city: $data['city'] ?? null,
            zip_code: $data['zip_code'] ?? null,
            state: $data['state'] ?? null,
            shipping_street_address: $data['shipping_street_address'] ?? null,
            shipping_country: $data['shipping_country'] ?? null,
            shipping_city: $data['shipping_city'] ?? null,
            shipping_zip_code: $data['shipping_zip_code'] ?? null,
            shipping_state: $data['shipping_state'] ?? null,
            cc: $data['cc'] ?? [],
            bcc: $data['bcc'] ?? [],
            legal_name: $data['legal_name'] ?? null,
            brand_name: $data['brand_name'] ?? null,
            registration_number: $data['registration_number'] ?? null,
            tax_number: $data['tax_number'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'bank_account' => $this->bank_account,
            'bank_code' => $this->bank_code,
            'email' => $this->email,
            'phone' => $this->phone,
            'full_name' => $this->full_name,
            'personal_code' => $this->personal_code,
            'street_address' => $this->street_address,
            'country' => $this->country,
            'city' => $this->city,
            'zip_code' => $this->zip_code,
            'state' => $this->state,
            'shipping_street_address' => $this->shipping_street_address,
            'shipping_country' => $this->shipping_country,
            'shipping_city' => $this->shipping_city,
            'shipping_zip_code' => $this->shipping_zip_code,
            'shipping_state' => $this->shipping_state,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'legal_name' => $this->legal_name,
            'brand_name' => $this->brand_name,
            'registration_number' => $this->registration_number,
            'tax_number' => $this->tax_number,
        ];

        // Filter out null values, empty arrays, and empty strings to avoid sending unnecessary fields to CHIP API
        return array_filter($data, fn ($value) => $value !== null && $value !== [] && $value !== '');
    }
}
