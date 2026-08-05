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

final class SuspendOrganizationAction
{
    use AsAction;

    public function handle(Organization $organization, Model $actor): Organization
    {
        app(OrganizationAuthorization::class)->authorize($actor, $organization, 'organization.change-status');

        return DB::transaction(function () use ($actor, $organization): Organization {
            $from = $organization->status;

            if ($from !== OrganizationStatus::Suspended) {
                $organization->transitionToStatus(OrganizationStatus::Suspended, now());
                $organization->save();
                app(OrganizationLifecycleHook::class)->statusChanged($organization, $from, OrganizationStatus::Suspended, $actor);
            }

            return $organization->fresh();
        });
    }
}
