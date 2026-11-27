<?php

declare(strict_types=1);

namespace AIArmada\Communications\Runtime;

/** Internal Eloquent relation and audit composition. */
interface TemplateRelationModels
{
    /** @return class-string<\AIArmada\Communications\Features\Templates\Models\CommunicationTemplate> */
    public function template(): string;

    /** @return class-string<\AIArmada\Communications\Features\Templates\Models\CommunicationTemplateVersion> */
    public function version(): string;
}
