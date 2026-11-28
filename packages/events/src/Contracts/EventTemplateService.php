<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventTemplateService
{
    /**
     * @return array<string, string>
     */
    public function publishedEventOptions(string $search): array;

    public function publishedEventLabel(string $templateId): ?string;

    public function createFromPublishedEventTemplate(string $templateId, mixed $actor): string;

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function createFromTemplate(string $templateId, array $overrides = []): string;
}
