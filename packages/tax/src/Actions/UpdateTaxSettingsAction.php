<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Tax\Settings\TaxSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final readonly class UpdateTaxSettingsAction
{
    public function __construct(private TaxSettings $settings) {}

    /**
     * @param  array<string, mixed>  $state
     */
    public function execute(?Authenticatable $actor, array $state): void
    {
        Gate::forUser($actor)->authorize('tax.settings.manage');

        if (config('tax.features.owner.enabled', false) && (OwnerContext::resolve() !== null || ! OwnerContext::isExplicitGlobal())) {
            throw new AuthorizationException('Global tax settings require an explicit global owner context.');
        }

        $rate = Arr::get($state, 'defaultTaxRate', 0.0);

        if (! is_numeric($rate) || ! is_finite((float) $rate) || (float) $rate < 0 || (float) $rate > 100) {
            throw new InvalidArgumentException('The default tax rate must be between 0 and 100.');
        }

        $taxIdLabel = Arr::get($state, 'taxIdLabel', 'Tax ID');

        if (! in_array($taxIdLabel, ['VAT Number', 'GST Number', 'SST Number', 'Tax ID', 'ABN', 'EIN'], true)) {
            throw new InvalidArgumentException('Unsupported tax ID label.');
        }

        $this->settings->enabled = (bool) Arr::get($state, 'enabled', true);
        $this->settings->defaultTaxRate = (float) Arr::get($state, 'defaultTaxRate', 0.0);
        $this->settings->defaultTaxName = (string) Arr::get($state, 'defaultTaxName', 'Tax');
        $this->settings->pricesIncludeTax = (bool) Arr::get($state, 'pricesIncludeTax', false);
        $this->settings->taxBasedOnShippingAddress = (bool) Arr::get($state, 'taxBasedOnShippingAddress', true);
        $this->settings->digitalGoodsTaxable = (bool) Arr::get($state, 'digitalGoodsTaxable', true);
        $this->settings->shippingTaxable = (bool) Arr::get($state, 'shippingTaxable', false);
        $this->settings->taxIdLabel = $taxIdLabel;
        $this->settings->validateTaxIds = (bool) Arr::get($state, 'validateTaxIds', false);
        $this->settings->requireExemptionCertificate = (bool) Arr::get($state, 'requireExemptionCertificate', false);

        $this->settings->save();
    }
}
