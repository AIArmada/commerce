<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Rules;

use AIArmada\Addressing\Support\ModelResolver;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;

final class StateBelongsToCountry implements ValidationRule
{
    public function __construct(private readonly ?string $countryCode) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($this->countryCode === null || mb_trim($this->countryCode) === '') {
            $fail('Select a country before selecting a state or federal territory.');

            return;
        }

        $stateClass = ModelResolver::stateClass();
        $valid = $stateClass::query()
            ->whereKey($value)
            ->whereHas('country', fn (Builder $query): Builder => $query->where('iso2', mb_strtoupper(mb_trim($this->countryCode))))
            ->exists();

        if (! $valid) {
            $fail('The selected state or federal territory must belong to the selected country.');
        }
    }
}
