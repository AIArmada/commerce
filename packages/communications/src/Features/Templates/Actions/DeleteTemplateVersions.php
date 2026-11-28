<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Templates\Actions;

use AIArmada\Communications\Features\Templates\Models\CommunicationTemplate;
use AIArmada\Communications\Features\Templates\Models\CommunicationTemplateVersion;
use RuntimeException;

final readonly class DeleteTemplateVersions
{
    public function execute(CommunicationTemplate $template): void
    {
        $template->getConnection()->transaction(function () use ($template): void {
            $template->versions()->eachById(static function (CommunicationTemplateVersion $version): void {
                if (! $version->delete()) {
                    throw new RuntimeException('Template version deletion was cancelled.');
                }
            });
        });
    }
}
