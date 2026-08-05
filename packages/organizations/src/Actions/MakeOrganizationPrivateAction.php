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

final class MakeOrganizationPrivateAction
{
    use AsAction;

    public function handle(Organization $organization, Model $actor): Organization
    {
        app(OrganizationVisibilityTransitionAuthorizer::class)->authorize($actor, $organization, OrganizationVisibility::Private);

        return DB::transaction(function () use ($actor, $organization): Organization {
            $from = $organization->visibility;

            if ($from !== OrganizationVisibility::Private) {
                $organization->transitionToVisibility(OrganizationVisibility::Private, now());
                $organization->save();
                app(OrganizationLifecycleHook::class)->visibilityChanged($organization, $from, OrganizationVisibility::Private, $actor);
            }

            return $organization->fresh();
        });
    }
}
