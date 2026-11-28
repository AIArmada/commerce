<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Data;

/**
 * Address data for JNT Express shipments.
 */
final readonly class AddressData
{
    public function __construct(
        public string $name,
        public string $phone,
        public string $address,
        public string $postCode,
        public string $countryCode = 'MYS',
        public ?string $state = null,
        public ?string $city = null,
        public ?string $area = null,
        public ?string $email = null,
        public ?string $idCard = null,
    ) {}

    /**
     * Create from JNT API response array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromApiArray(array $data): self
    {
        return new self(
            name: $data['name'],
            phone: $data['phone'],
            address: $data['address'],
            postCode: $data['postCode'],
            countryCode: $data['countryCode'] ?? 'MYS',
            state: $data['prov'] ?? null,
            city: $data['city'] ?? null,
            area: $data['area'] ?? null,
            email: $data['email'] ?? null,
            idCard: $data['idCard'] ?? null,
        );
    }

    /**
     * Convert to JNT API request array.
     *
     * @return array<string, string>
     */
    public function toApiArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'postCode' => $this->postCode,
            'countryCode' => $this->countryCode,
            'prov' => $this->state,
            'city' => $this->city,
            'area' => $this->area,
            'email' => $this->email,
            'idCard' => $this->idCard,
        ], fn (?string $value): bool => $value !== null);
    }

    public function getFullAddress(): string
    {
        return implode(', ', array_filter([
            $this->address,
            $this->area,
            $this->city,
            $this->state,
            $this->postCode,
            $this->countryCode,
        ]));
    }
}
