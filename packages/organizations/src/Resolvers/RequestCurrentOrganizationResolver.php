<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Resolvers;

use AIArmada\Organizations\Contracts\CurrentOrganizationResolver;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Stateless request-based organization resolver for Octane-safe tenancy.
 */
final readonly class RequestCurrentOrganizationResolver implements CurrentOrganizationResolver
{
    public function resolve(): ?Organization
    {
        $actor = Auth::user();

        if (! $actor instanceof Model) {
            return null;
        }

        $request = request();
        $organizationKey = $request->header('X-Organization-Id')
            ?: $request->query('organization_id')
            ?: $request->route('organization');

        if ($organizationKey instanceof Organization) {
            $organizationKey = $organizationKey->getKey();
        }

        if (! is_string($organizationKey) || mb_trim($organizationKey) === '') {
            return null;
        }

        return Organization::query()
            ->whereKey($organizationKey)
            ->whereHas('members', static fn (Builder $query): Builder => $query->whereKey($actor->getKey()))
            ->first();
    }
}
