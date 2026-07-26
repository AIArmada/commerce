<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Rules;

use AIArmada\Addressing\Models\AddressArea;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class AddressAreasBelongToCountry implements ValidationRule
{
    public function __construct(private readonly ?string $countryCode) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $ids = is_array($value) ? array_values(array_filter($value)) : array_filter([$value]);

        if ($ids === []) {
            return;
        }

        if ($this->countryCode === null || mb_trim($this->countryCode) === '') {
            $fail('Select a country before selecting an address area.');

            return;
        }

        $areaClass = config('filament-addressing.resources.areas.model', AddressArea::class);
        $count = $areaClass::query()
            ->where('country_code', mb_strtoupper(mb_trim($this->countryCode)))
            ->whereIn('id', $ids)
            ->count();

        if ($count !== count(array_unique($ids))) {
            $fail('All selected address areas must belong to the selected country.');
        }
    }
}
