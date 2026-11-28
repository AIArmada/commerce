<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\Pricing\Settings\PricingSettings;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class UpdatePricingSettings
{
    /** @param array<string, mixed> $state */
    public function execute(array $state): void
    {
        /** @var array<string, mixed> $validated */
        $validated = Validator::make($state, [
            'defaultCurrency' => ['required', Rule::in(['MYR', 'USD', 'EUR', 'GBP', 'SGD', 'THB', 'IDR', 'PHP'])],
            'decimalPlaces' => ['required', 'integer', 'min:0', 'max:4'],
            'roundingMode' => ['required', Rule::in(['up', 'down', 'half_up', 'half_down'])],
            'pricesIncludeTax' => ['required', 'boolean'],
            'minimumOrderValue' => ['required', 'integer', 'min:0'],
            'maximumOrderValue' => ['required', 'integer', 'min:0'],
            'promotionalPricingEnabled' => ['required', 'boolean'],
            'tieredPricingEnabled' => ['required', 'boolean'],
            'customerGroupPricingEnabled' => ['required', 'boolean'],
        ])->validate();

        if ((int) $validated['maximumOrderValue'] !== 0 && (int) $validated['maximumOrderValue'] < (int) $validated['minimumOrderValue']) {
            throw ValidationException::withMessages(['maximumOrderValue' => 'The maximum order value must not be below the minimum.']);
        }

        $settings = app(PricingSettings::class);

        $settings->defaultCurrency = (string) Arr::get($validated, 'defaultCurrency', 'MYR');
        $settings->decimalPlaces = (int) Arr::get($validated, 'decimalPlaces', 2);
        $settings->roundingMode = (string) Arr::get($validated, 'roundingMode', 'half_up');
        $settings->pricesIncludeTax = (bool) Arr::get($validated, 'pricesIncludeTax', false);
        $settings->minimumOrderValue = (int) Arr::get($validated, 'minimumOrderValue', 0);
        $settings->maximumOrderValue = (int) Arr::get($validated, 'maximumOrderValue', 0);
        $settings->promotionalPricingEnabled = (bool) Arr::get($validated, 'promotionalPricingEnabled', true);
        $settings->tieredPricingEnabled = (bool) Arr::get($validated, 'tieredPricingEnabled', true);
        $settings->customerGroupPricingEnabled = (bool) Arr::get($validated, 'customerGroupPricingEnabled', false);

        $settings->save();
    }
}
