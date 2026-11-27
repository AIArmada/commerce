<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

final readonly class IssuerDetailsData
{
    public function __construct(
        public ?string $website,
        public ?string $legal_street_address,
        public ?string $legal_country,
        public ?string $legal_city,
        public ?string $legal_zip_code,
        public ?string $legal_state,
        /** @var array<string, mixed> */
        public array $bank_accounts,
        public ?string $legal_name,
        public ?string $brand_name,
        public ?string $registration_number,
        public ?string $tax_number,
    ) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
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
