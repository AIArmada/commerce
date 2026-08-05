<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Actions;

use AIArmada\Organizations\Contracts\OrganizationLifecycleHook;
use AIArmada\Organizations\Contracts\OrganizationVisibilityTransitionAuthorizer;
use AIArmada\Organizations\Enums\OrganizationVisibility;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

final class MakeOrganizationPublicAction
{
    use AsAction;

    public function handle(Organization $organization, Model $actor): Organization
    {
        app(OrganizationVisibilityTransitionAuthorizer::class)->authorize($actor, $organization, OrganizationVisibility::Public);

        return DB::transaction(function () use ($actor, $organization): Organization {
            $from = $organization->visibility;

            if ($from !== OrganizationVisibility::Public) {
                $organization->transitionToVisibility(OrganizationVisibility::Public, now());
                $organization->save();
                app(OrganizationLifecycleHook::class)->visibilityChanged($organization, $from, OrganizationVisibility::Public, $actor);
            }

            return $organization->fresh();
        });
    }
}
