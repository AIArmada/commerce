<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Taxonomy\Extensions;

use AIArmada\Events\Contracts\EventScopeCascadeDeleter;
use AIArmada\Events\Features\Taxonomy\Actions\DeleteTaxonomyScopeDependents;

final class TaxonomyDeleteCascadeExtension implements EventScopeCascadeDeleter
{
    public function __construct(
        private readonly DeleteTaxonomyScopeDependents $deleteScopeDependents,
    ) {}

    public function deleteForScope(string $scopeType, string $scopeId, ?string $morphClass = null): void
    {
        $this->deleteScopeDependents->deleteForScope($scopeType, $scopeId);
    }
}
