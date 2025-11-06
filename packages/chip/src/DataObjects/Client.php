<?php

declare(strict_types=1);

namespace AIArmada\Chip\DataObjects;

use Carbon\Carbon;

final class Client
{
    /**
     * @param  array<int, mixed>  $cc
     * @param  array<int, mixed>  $bcc
     * @param  array<int, mixed>  $delivery_methods
     * @param  array<string, mixed>|null  $address_data
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly int $created_on,
        public readonly int $updated_on,
        public readonly ?string $client_type,
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
        public readonly array $delivery_methods,
        public readonly ?string $legal_name,
        public readonly ?string $brand_name,
        public readonly ?string $registration_number,
        public readonly ?string $tax_number,
        // Private properties for API response handling
        private readonly ?array $address_data = null,
        private readonly ?string $identity_type = null,
        private readonly ?string $identity_number = null,
    ) {}

    /**
     * Magic property accessor for convenient camelCase access to snake_case properties
     *
     * @param  string  $name
     * @return mixed
     */
    public function __get($name)
    {
        return match ($name) {
            'fullName' => $this->full_name,
            'personalCode' => $this->personal_code,
            'streetAddress' => $this->street_address,
            'zipCode' => $this->zip_code,
            'legalName' => $this->legal_name,
            'brandName' => $this->brand_name,
            'registrationNumber' => $this->registration_number,
            'taxNumber' => $this->tax_number,
            'address' => $this->address_data,
            'identityType' => $this->identity_type,
            'identityNumber' => $this->identity_number,
            default => null,
        };
    }

    /**
     * @param  string  $name
     */
    public function __isset($name): bool
    {
        return in_array($name, ['fullName', 'personalCode', 'streetAddress', 'zipCode', 'legalName', 'brandName', 'registrationNumber', 'taxNumber', 'address', 'identityType', 'identityNumber']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'] ?? 'client',
            created_on: (int) ($data['created_on'] ?? strtotime($data['created_at'] ?? 'now')),
            updated_on: (int) ($data['updated_on'] ?? strtotime($data['updated_at'] ?? 'now')),
            client_type: $data['client_type'] ?? null,
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
            delivery_methods: $data['delivery_methods'] ?? [],
            legal_name: $data['legal_name'] ?? null,
            brand_name: $data['brand_name'] ?? null,
            registration_number: $data['registration_number'] ?? null,
            tax_number: $data['tax_number'] ?? null,
            address_data: $data['address'] ?? null,
            identity_type: $data['identity_type'] ?? null,
            identity_number: $data['identity_number'] ?? null,
        );
    }

    public function getCreatedAt(): Carbon
    {
        return Carbon::createFromTimestamp($this->created_on);
    }

    public function getUpdatedAt(): Carbon
    {
        return Carbon::createFromTimestamp($this->updated_on);
    }

    public function isCompany(): bool
    {
        return ! empty($this->legal_name) || ! empty($this->registration_number);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'created_on' => $this->created_on,
            'updated_on' => $this->updated_on,
            'client_type' => $this->client_type,
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
            'delivery_methods' => $this->delivery_methods,
            'legal_name' => $this->legal_name,
            'brand_name' => $this->brand_name,
            'registration_number' => $this->registration_number,
            'tax_number' => $this->tax_number,
        ];
    }
}
