<?php

declare(strict_types=1);

namespace AIArmada\Docs\Runtime;

/** @phpstan-type RelatedDocTemplate \AIArmada\Docs\Features\Templates\Models\DocTemplate */
interface TemplateRelationModel
{
    /** @return class-string<RelatedDocTemplate> */
    public function template(): string;
}
