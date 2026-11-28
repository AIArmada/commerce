<?php

declare(strict_types=1);

namespace AIArmada\Communications\Runtime;

/**
 * Internal Eloquent relation and audit composition.
 *
 * @phpstan-type RelatedCommunicationTemplate \AIArmada\Communications\Features\Templates\Models\CommunicationTemplate
 * @phpstan-type RelatedCommunicationTemplateVersion \AIArmada\Communications\Features\Templates\Models\CommunicationTemplateVersion
 */
interface TemplateRelationModels
{
    /** @return class-string<RelatedCommunicationTemplate> */
    public function template(): string;

    /** @return class-string<RelatedCommunicationTemplateVersion> */
    public function version(): string;
}
