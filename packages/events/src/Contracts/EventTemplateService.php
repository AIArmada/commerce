<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventTemplateService
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    public function createFromTemplate(string $templateId, array $overrides = []): string;
}
