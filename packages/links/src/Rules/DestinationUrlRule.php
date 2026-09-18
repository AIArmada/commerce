<?php

declare(strict_types=1);

namespace AIArmada\Links\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class DestinationUrlRule implements ValidationRule
{
    /**
     * @param  list<string>|null  $allowedHosts
     */
    public function __construct(
        private readonly ?bool $requireHttps = null,
        private readonly ?array $allowedHosts = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        $parts = parse_url($value) ?: [];
        $scheme = mb_strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('The :attribute scheme must be http or https.');

            return;
        }

        if (($this->requireHttps ?? (bool) config('links.features.security.require_https', true)) && $scheme !== 'https') {
            $fail('The :attribute must use https.');

            return;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            $fail('The :attribute must not contain credentials.');

            return;
        }

        $allowedHosts = $this->allowedHosts ?? config('links.features.security.allowed_hosts', []);

        if (is_array($allowedHosts) && $allowedHosts !== []) {
            $host = mb_strtolower((string) ($parts['host'] ?? ''));

            if (! in_array($host, array_map(mb_strtolower(...), $allowedHosts), true)) {
                $fail('The :attribute host is not allowed.');
            }
        }
    }
}
