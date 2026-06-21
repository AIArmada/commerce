<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

use AIArmada\Communications\Data\RenderedContentData;
use AIArmada\Communications\Models\CommunicationTemplate;

interface ContentRenderer
{
    public function render(
        CommunicationTemplate $template,
        string $channel,
        ?string $locale,
        array $variables,
    ): RenderedContentData;

    public function renderFromNotification(
        mixed $notifiable,
        mixed $notification,
        string $channel,
    ): RenderedContentData;
}
