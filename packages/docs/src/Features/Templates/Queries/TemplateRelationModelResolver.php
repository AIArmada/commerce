<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Templates\Queries;

use AIArmada\Docs\Features\Templates\Models\DocTemplate;
use AIArmada\Docs\Runtime\TemplateRelationModel;

final readonly class TemplateRelationModelResolver implements TemplateRelationModel
{
    public function template(): string
    {
        return DocTemplate::class;
    }
}
