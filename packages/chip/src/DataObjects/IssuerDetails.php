<?php

declare(strict_types=1);

namespace AIArmada\Chip\DataObjects;

final class IssuerDetails
{
    public function __construct(
        public readonly ?string $website,
        public readonly ?string $legal_street_address,
        public readonly ?string $legal_country,
        public readonly ?string $legal_city,
        public readonly ?string $legal_zip_code,
        public readonly ?string $legal_state,
        /** @var array<string, mixed> */
        public readonly array $bank_accounts,
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
            website: $data['website'] ?? null,
            legal_street_address: $data['legal_street_address'] ?? null,
            legal_country: $data['legal_country'] ?? null,
            legal_city: $data['legal_city'] ?? null,
            legal_zip_code: $data['legal_zip_code'] ?? null,
            legal_state: $data['legal_state'] ?? null,
            bank_accounts: $data['bank_accounts'] ?? [],
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
        return [
            'website' => $this->website,
            'legal_street_address' => $this->legal_street_address,
            'legal_country' => $this->legal_country,
            'legal_city' => $this->legal_city,
            'legal_zip_code' => $this->legal_zip_code,
            'legal_state' => $this->legal_state,
            'bank_accounts' => $this->bank_accounts,
            'legal_name' => $this->legal_name,
            'brand_name' => $this->brand_name,
            'registration_number' => $this->registration_number,
            'tax_number' => $this->tax_number,
        ];
    }
}
