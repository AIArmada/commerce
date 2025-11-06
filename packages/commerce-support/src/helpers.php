<?php

declare(strict_types=1);

if (! function_exists('commerce_config')) {
    /**
     * Get commerce configuration value with dot notation.
     *
     * @param  string  $key  Configuration key (e.g., 'cart.storage', 'chip.api_key')
     * @param  mixed  $default  Default value if key doesn't exist
     */
    function commerce_config(string $key, mixed $default = null): mixed
    {
        return config($key, $default);
    }
}

if (! function_exists('commerce_json_column_type')) {
    /**
     * Resolve the preferred JSON column type for a package.
     *
     * @param  string|null  $packageKey  e.g. 'vouchers', 'chip', 'docs' (used to read {PKG}_JSON_COLUMN_TYPE)
     * @param  string  $default  Fallback when no env is set
     */
    function commerce_json_column_type(?string $packageKey = null, string $default = 'json'): string
    {
        $global = env('COMMERCE_JSON_COLUMN_TYPE');

        if ($packageKey !== null) {
            $envKey = mb_strtoupper($packageKey).'_JSON_COLUMN_TYPE';
            $packageSpecific = env($envKey);

            if (is_string($packageSpecific) && $packageSpecific !== '') {
                return $packageSpecific;
            }
        }

        if (is_string($global) && $global !== '') {
            return $global;
        }

        return $default;
    }
}
