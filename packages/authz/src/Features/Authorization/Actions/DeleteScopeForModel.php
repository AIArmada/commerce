<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Actions;

use AIArmada\Authz\Features\Authorization\Models\AuthzScope;
use Illuminate\Database\Eloquent\Model;

final readonly class DeleteScopeForModel
{
    public function __construct(private DeleteAuthzScope $deleteScope) {}

    public function execute(Model $scopeable): void
    {
        $scope = AuthzScope::query()
            ->where('scopeable_type', $scopeable->getMorphClass())
            ->where('scopeable_id', $scopeable->getKey())
            ->first();

        if ($scope !== null) {
            $this->deleteScope->execute((string) $scope->getKey());
        }
    }
}
