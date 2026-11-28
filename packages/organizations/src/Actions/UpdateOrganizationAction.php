<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Actions;

use AIArmada\Organizations\Contracts\OrganizationAuthorization;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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
            if (array_key_exists('name', $attributes)) {
                $name = $attributes['name'];

                if (! is_string($name) || mb_trim($name) === '' || mb_strlen(mb_trim($name)) > 255) {
                    throw new InvalidArgumentException('An organization name of at most 255 characters is required.');
                }

                $attributes['name'] = mb_trim($name);
            }

            if (array_key_exists('slug', $attributes)) {
                $slug = $attributes['slug'];

                if (! is_string($slug) || mb_trim($slug) === '' || mb_strlen($slug) > 255) {
                    throw new InvalidArgumentException('An organization slug of at most 255 characters is required.');
                }
            }

            if (isset($attributes['description']) && ! is_string($attributes['description'])) {
                throw new InvalidArgumentException('The organization description must be a string.');
            }

            $locked->fill($attributes);
            $locked->save();

            return $locked;
        });
    }
}
