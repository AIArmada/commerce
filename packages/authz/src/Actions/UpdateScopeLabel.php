<?php

declare(strict_types=1);

namespace AIArmada\Authz\Actions;

use AIArmada\Authz\Models\AuthzScope;
use Illuminate\Database\Eloquent\Model;

final readonly class UpdateScopeLabel
{
    public function execute(Model $scopeable, ?string $label): void
    {
        $scope = AuthzScope::query()
            ->where('scopeable_type', $scopeable->getMorphClass())
            ->where('scopeable_id', $scopeable->getKey())
            ->first();

        if ($scope !== null && $scope->label !== $label) {
            $scope->forceFill(['label' => $label])->save();
        }
    }
}
