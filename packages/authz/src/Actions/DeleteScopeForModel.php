<?php

declare(strict_types=1);

namespace AIArmada\Authz\Actions;

use AIArmada\Authz\Models\AuthzScope;
use Illuminate\Database\Eloquent\Model;

final readonly class DeleteScopeForModel
{
    public function execute(Model $scopeable): void
    {
        AuthzScope::query()
            ->where('scopeable_type', $scopeable->getMorphClass())
            ->where('scopeable_id', $scopeable->getKey())
            ->first()?->delete();
    }
}
