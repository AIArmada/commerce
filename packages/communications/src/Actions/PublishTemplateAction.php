<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Enums\TemplateStatus;
use AIArmada\Communications\Events\TemplatePublished;
use AIArmada\Communications\Models\CommunicationTemplate;
use AIArmada\Communications\Models\CommunicationTemplateVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use RuntimeException;

final class PublishTemplateAction
{
    public function handle(string $versionId): CommunicationTemplateVersion
    {
        $version = CommunicationTemplateVersion::query()->findOrFail($versionId);

        if ($version->published_at !== null) {
            throw new RuntimeException("Template version {$versionId} is already published.");
        }

        $version->published_at = CarbonImmutable::now();
        $version->save();

        $template = CommunicationTemplate::query()->findOrFail($version->template_id);
        $template->status = TemplateStatus::Published;
        $template->published_at = CarbonImmutable::now();
        $template->save();

        Event::dispatch(new TemplatePublished(
            templateId: $template->id,
            versionId: $version->id,
            version: $version->version,
            channel: $version->channel,
        ));

        return $version;
    }
}
