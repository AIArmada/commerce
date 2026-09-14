<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Actions;

use AIArmada\Organizations\Contracts\OrganizationAuthorization;
use AIArmada\Organizations\Contracts\OrganizationLifecycleHook;
use AIArmada\Organizations\Enums\OrganizationStatus;
use AIArmada\Organizations\Enums\OrganizationVisibility;
use AIArmada\Organizations\Models\Organization;
use AIArmada\Organizations\Support\OrganizationStateTransition;
use Carbon\CarbonImmutable;
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
                app(OrganizationStateTransition::class)->status(
                    $organization,
                    OrganizationStatus::Active,
                    CarbonImmutable::now(),
                );

                if ($organization->visibility === OrganizationVisibility::Public) {
                    app(OrganizationStateTransition::class)->visibility(
                        $organization,
                        OrganizationVisibility::Private,
                        CarbonImmutable::now(),
                    );
                    $organization->save();
                    app(OrganizationLifecycleHook::class)->visibilityChanged($organization, OrganizationVisibility::Public, OrganizationVisibility::Private, $actor);
                } else {
                    $organization->save();
                }

                app(OrganizationLifecycleHook::class)->statusChanged($organization, $from, OrganizationStatus::Active, $actor);
            }

            return $organization->fresh();
        });
    }
}
