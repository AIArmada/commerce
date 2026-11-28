<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Actions;

use AIArmada\Organizations\Extensions\OrganizationLifecycleHook;
use AIArmada\Organizations\Authorization\OrganizationVisibilityTransitionAuthorizer;
use AIArmada\Organizations\Enums\OrganizationStatus;
use AIArmada\Organizations\Enums\OrganizationVisibility;
use AIArmada\Organizations\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

final class MakeOrganizationPublicAction
{
    use AsAction;

    public function __construct(
        private readonly OrganizationVisibilityTransitionAuthorizer $authorization,
        private readonly OrganizationLifecycleHook $lifecycleHook,
    ) {}

    public function handle(Organization $organization, Model $actor): Organization
    {
        return DB::transaction(function () use ($actor, $organization): Organization {
            $locked = $organization->newQuery()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();
            $organization->setRawAttributes($locked->getAttributes(), true)->unsetRelations();
            $this->authorization->authorize($actor, $organization, OrganizationVisibility::Public);

            if ($organization->status !== OrganizationStatus::Active) {
                throw new LogicException('Only active organizations can be made public.');
            }

            $from = $organization->visibility;

            if ($from !== OrganizationVisibility::Public) {
                $organization->transitionToVisibility(
                    OrganizationVisibility::Public,
                    CarbonImmutable::now(),
                );
                $organization->save();
                $this->lifecycleHook->visibilityChanged($organization, $from, OrganizationVisibility::Public, $actor);
            }

            return $organization->fresh();
        });
    }
}
