<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Analytics\Api;

interface DeletesFormAnalytics
{
    public function execute(string $formId): void;
}
