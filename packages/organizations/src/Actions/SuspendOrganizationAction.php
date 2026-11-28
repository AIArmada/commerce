<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Actions;

use AIArmada\Organizations\Authorization\OrganizationAuthorization;
use AIArmada\Organizations\Contracts\OrganizationLifecycleHook;
use AIArmada\Organizations\Enums\OrganizationStatus;
use AIArmada\Organizations\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

final class SuspendOrganizationAction
{
    use AsAction;

    public function __construct(
        private readonly OrganizationAuthorization $authorization,
        private readonly OrganizationLifecycleHook $lifecycleHook,
    ) {}

    public function handle(Organization $organization, Model $actor): Organization
    {
        return DB::transaction(function () use ($actor, $organization): Organization {
            $locked = $organization->newQuery()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();
            $organization->setRawAttributes($locked->getAttributes(), true)->unsetRelations();
            $this->authorization->authorize($actor, $organization, 'organization.change-status');

            $from = $organization->status;

            if ($from !== OrganizationStatus::Suspended) {
                $organization->transitionToStatus(
                    OrganizationStatus::Suspended,
                    CarbonImmutable::now(),
                );
                $organization->save();
                $this->lifecycleHook->statusChanged($organization, $from, OrganizationStatus::Suspended, $actor);
            }

            return $organization->fresh();
        });
    }
}
