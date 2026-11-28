<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Templates\Queries;

use AIArmada\Communications\Features\Templates\Models\CommunicationTemplate;
use AIArmada\Communications\Features\Templates\Models\CommunicationTemplateVersion;
use AIArmada\Communications\Runtime\TemplateRelationModels;

final readonly class TemplateRelationModelsResolver implements TemplateRelationModels
{
    public function template(): string
    {
        return CommunicationTemplate::class;
    }

    public function version(): string
    {
        return CommunicationTemplateVersion::class;
    }
}
