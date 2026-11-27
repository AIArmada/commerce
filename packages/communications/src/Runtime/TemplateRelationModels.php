<?php

declare(strict_types=1);

namespace AIArmada\Communications\Runtime;

use AIArmada\Communications\Features\Templates\Models\CommunicationTemplate;
use AIArmada\Communications\Features\Templates\Models\CommunicationTemplateVersion;

/** Internal Eloquent relation and audit composition. */
interface TemplateRelationModels
{
    /** @return class-string<CommunicationTemplate> */
    public function template(): string;

    /** @return class-string<CommunicationTemplateVersion> */
    public function version(): string;
}
