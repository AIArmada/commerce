<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Rules;

use AIArmada\Addressing\Support\ModelResolver;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;

final class CityBelongsToCountryAndState implements ValidationRule
{
    public function __construct(private readonly ?string $countryCode, private readonly ?string $stateId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($this->countryCode === null || mb_trim($this->countryCode) === '') {
            $fail('Select a country before selecting a city.');

            return;
        }

        $cityClass = ModelResolver::cityClass();
        $valid = $cityClass::query()
            ->whereKey($value)
            ->whereHas('country', fn (Builder $query): Builder => $query->where('iso2', mb_strtoupper(mb_trim($this->countryCode))))
            ->where('state_id', $this->stateId)
            ->exists();

        if (! $valid) {
            $fail('The selected city must belong to the selected country and state.');
        }
    }
}
