<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Actions;

use AIArmada\Communications\Contracts\ContentRenderer;
use AIArmada\Communications\Data\RenderedContentData;

final class RenderCommunicationContentAction
{
    public function __construct(
        private readonly ContentRenderer $renderer,
    ) {}

    public function handle(
        string $templateId,
        string $channel,
        ?string $locale = null,
        array $variables = [],
    ): RenderedContentData {
        return $this->renderer->render($templateId, $channel, $locale, $variables);
    }
}
