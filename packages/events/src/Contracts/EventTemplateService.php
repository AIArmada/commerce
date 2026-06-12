<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventTemplate;

interface EventTemplateService
{
    public function createFromTemplate(EventTemplate $template, array $overrides = []): mixed;
}
