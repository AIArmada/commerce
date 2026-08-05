<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Actions;

use AIArmada\Organizations\Contracts\OrganizationAuthorization;
use AIArmada\Organizations\Contracts\OrganizationLifecycleHook;
use AIArmada\Organizations\Enums\OrganizationStatus;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

final class RestoreOrganizationAction
{
    use AsAction;

    public function handle(Organization $organization, Model $actor): Organization
    {
        app(OrganizationAuthorization::class)->authorize($actor, $organization, 'organization.change-status');

        return DB::transaction(function () use ($actor, $organization): Organization {
            $from = $organization->status;

            if ($from !== OrganizationStatus::Active) {
                $organization->transitionToStatus(OrganizationStatus::Active, now());
                $organization->save();
                app(OrganizationLifecycleHook::class)->statusChanged($organization, $from, OrganizationStatus::Active, $actor);
            }

            return $organization->fresh();
        });
    }
}
