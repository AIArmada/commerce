<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Arr;

/**
 * @property string|null $shipping_street_address
 * @property string|null $shipping_city
 * @property string|null $shipping_state
 * @property string|null $shipping_zip_code
 * @property string|null $shipping_country
 * @property string|null $legal_name
 * @property string|null $brand_name
 * @property string|null $registration_number
 * @property string|null $tax_number
 * @property string|null $bank_account
 * @property string|null $bank_code
 * @property array<string>|null $cc
 * @property array<string>|null $bcc
 */
final class ChipClient extends ChipModel
{
    public $incrementing = false;

    public $timestamps = true;

    protected $keyType = 'string';

    public function createdOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?\Illuminate\Support\Carbon => $this->toTimestamp($attributes['created_on'] ?? null));
    }

    public function updatedOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?\Illuminate\Support\Carbon => $this->toTimestamp($attributes['updated_on'] ?? null));
    }

    public function location(): Attribute
    {
        return Attribute::get(function (): ?string {
            $parts = array_filter([
                Arr::get($this->attributes, 'city'),
                Arr::get($this->attributes, 'state'),
                Arr::get($this->attributes, 'country'),
            ]);

            return $parts === [] ? null : implode(', ', $parts);
        });
    }

    public function shippingLocation(): Attribute
    {
        return Attribute::get(function (): ?string {
            $parts = array_filter([
                Arr::get($this->attributes, 'shipping_city'),
                Arr::get($this->attributes, 'shipping_state'),
                Arr::get($this->attributes, 'shipping_country'),
            ]);

            return $parts === [] ? null : implode(', ', $parts);
        });
    }

    protected static function tableSuffix(): string
    {
        return 'clients';
    }

    protected function casts(): array
    {
        return [
            'cc' => 'array',
            'bcc' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
