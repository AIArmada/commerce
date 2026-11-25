<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Taxonomy\Extensions;

use AIArmada\Events\Contracts\EventScopeCloner;
use AIArmada\Events\Features\Taxonomy\Actions\CloneTaxonomyScopeAction;

final class TaxonomyCloneScopeExtension implements EventScopeCloner
{
    public function __construct(
        private readonly CloneTaxonomyScopeAction $cloneScopeAction,
    ) {}

    public function cloneForScope(
        string $sourceScopeType,
        string $sourceScopeId,
        string $targetScopeType,
        string $targetScopeId,
        array $options = [],
    ): void {
        $this->cloneScopeAction->cloneForScope(
            $sourceScopeType,
            $sourceScopeId,
            $targetScopeType,
            $targetScopeId,
            $options,
        );
    }
}
