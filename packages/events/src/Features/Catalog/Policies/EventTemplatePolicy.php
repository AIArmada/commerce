<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Policies;

use AIArmada\Events\Features\Catalog\Models\EventTemplate;
use AIArmada\Events\Platform\ResolvesEventManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final class EventTemplatePolicy
{
    use ResolvesEventManager;

    public function viewAny(Authenticatable $actor): bool
    {
        return true;
    }

    public function view(Authenticatable $actor, EventTemplate $template): bool
    {
        return $this->canManageTemplate($actor, $template, 'view');
    }

    public function create(Authenticatable $actor): bool
    {
        return $this->canCreateForOwner($actor);
    }

    public function update(Authenticatable $actor, EventTemplate $template): bool
    {
        return $this->canManageTemplate($actor, $template, 'update');
    }

    public function delete(Authenticatable $actor, EventTemplate $template): bool
    {
        return $this->canManageTemplate($actor, $template, 'delete');
    }

    private function canManageTemplate(Authenticatable $actor, EventTemplate $template, string $ability): bool
    {
        if (! $this->eventOwnerScopeEnabled()) {
            return true;
        }

        $owner = $template->getRelationValue('owner');

        return $owner instanceof Model && $this->canManageOwnerTarget($actor, $ability, $owner);
    }
}
