<?php

declare(strict_types=1);

namespace AIArmada\Authz\Services;

/**
 * Resolves wildcard permission patterns like 'orders.*' to match 'orders.view', 'orders.create', etc.
 */
class WildcardPermissionResolver
{
    /**
     * Check if a permission string is a wildcard pattern.
     */
    public function isWildcard(string $permission): bool
    {
        return str_contains($permission, '*');
    }

    /**
     * Check if a specific permission matches a wildcard pattern.
     *
     * Every wildcard segment participates in the match. A pattern ending with
     * the configured separator followed by '*' matches the whole trailing
     * subtree; wildcards in other positions match exactly one segment.
     * Literal characters are quoted, and the configured permission separator
     * is honored.
     */
    public function matches(string $wildcardPattern, string $permission): bool
    {
        if ($wildcardPattern === $permission) {
            return true;
        }

        if ($wildcardPattern === '*') {
            return true;
        }

        if (! str_contains($wildcardPattern, '*')) {
            return false;
        }

        return preg_match($this->compile($wildcardPattern), $permission) === 1;
    }

    protected function compile(string $wildcardPattern): string
    {
        $separator = $this->separator();
        $quotedSeparator = preg_quote($separator, '/');
        $segment = '[^' . $quotedSeparator . ']+';

        $suffix = '';

        if (str_ends_with($wildcardPattern, $separator . '*')) {
            $wildcardPattern = mb_substr($wildcardPattern, 0, -(mb_strlen($separator) + 1));
            $suffix = $quotedSeparator . $segment . '(?:' . $quotedSeparator . $segment . ')*';
        }

        $pattern = str_replace('\*', $segment, preg_quote($wildcardPattern, '/'));

        return '/^' . $pattern . $suffix . '$/';
    }

    protected function separator(): string
    {
        $separator = config('authz.permissions.separator', '.');

        return is_string($separator) && $separator !== '' ? $separator : '.';
    }
}
