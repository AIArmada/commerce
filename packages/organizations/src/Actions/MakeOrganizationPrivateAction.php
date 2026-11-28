<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Actions;

use AIArmada\Organizations\Authorization\OrganizationVisibilityTransitionAuthorizer;
use AIArmada\Organizations\Enums\OrganizationVisibility;
use AIArmada\Organizations\Extensions\OrganizationLifecycleHook;
use AIArmada\Organizations\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class MakeOrganizationPrivateAction
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
            $this->authorization->authorize($actor, $organization, OrganizationVisibility::Private);

            $from = $organization->visibility;

            if ($from !== OrganizationVisibility::Private) {
                $organization->transitionToVisibility(
                    OrganizationVisibility::Private,
                    CarbonImmutable::now(),
                );
                if (! $organization->save()) {
                    throw new RuntimeException('Organization save was cancelled.');
                }
                $this->lifecycleHook->visibilityChanged($organization, $from, OrganizationVisibility::Private, $actor);
            }

            return $organization->fresh();
        });
    }
}
