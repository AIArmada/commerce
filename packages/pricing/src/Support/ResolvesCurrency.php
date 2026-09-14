<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Support;

use Illuminate\Support\Arr;

trait ResolvesCurrency
{
    /**
     * @param  array<string, mixed>  $context
     */
    protected function resolveCurrency(array $context): string
    {
        $currency = Arr::get($context, 'currency');

        if (is_string($currency) && $currency !== '') {
            return $currency;
        }

        return (string) config('pricing.defaults.currency', 'MYR');
    }
}
