<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Contracts\ContentRenderer;
use AIArmada\Communications\Data\RenderedContentData;
use AIArmada\Communications\Models\CommunicationTemplate;

final class RenderCommunicationContentAction
{
    public function __construct(
        private readonly ContentRenderer $renderer,
    ) {}

    public function handle(
        CommunicationTemplate $template,
        string $channel,
        ?string $locale = null,
        array $variables = [],
    ): RenderedContentData {
        return $this->renderer->render($template, $channel, $locale, $variables);
    }
}
