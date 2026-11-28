<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Actions;

use AIArmada\Organizations\Contracts\OrganizationAuthorization;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final readonly class UpdateOrganizationAction
{
    public function __construct(private OrganizationAuthorization $authorization) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Organization $organization, Model $actor, array $attributes): Organization
    {
        return DB::transaction(function () use ($organization, $actor, $attributes): Organization {
            $locked = $organization->newQuery()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();
            $this->authorization->authorize($actor, $locked, 'organization.update');
            $locked->fill($attributes);
            $locked->save();

            return $locked;
        });
    }
}
