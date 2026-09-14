<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Support;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the acting user for reusable engagement actions.
 *
 * Filament tables are normally authenticated, but these actions are public
 * API and may be reused on guest-visible tables. Failing with an
 * authentication exception beats a TypeError deep inside a manager call.
 */
final class AuthenticatedUser
{
    public static function resolve(): Model
    {
        $user = auth()->user();

        if (! $user instanceof Model) {
            throw new AuthenticationException('You must be signed in to perform this action.');
        }

        return $user;
    }
}
