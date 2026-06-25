<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Enums\TemplateStatus;
use AIArmada\Communications\Events\TemplatePublished;
use AIArmada\Communications\Models\CommunicationTemplate;
use AIArmada\Communications\Models\CommunicationTemplateVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;

final class PublishTemplateAction
{
    public function handle(string $versionId): CommunicationTemplateVersion
    {
        [$version, $template] = DB::transaction(function () use ($versionId): array {
            $version = CommunicationTemplateVersion::query()
                ->lockForUpdate()
                ->findOrFail($versionId);

            if ($version->published_at !== null) {
                throw new RuntimeException("Template version {$versionId} is already published.");
            }

            $template = CommunicationTemplate::query()
                ->lockForUpdate()
                ->findOrFail($version->template_id);

            $publishedAt = CarbonImmutable::now();
            $version->published_at = $publishedAt;
            $version->save();

            $template->status = TemplateStatus::Published;
            $template->published_at = $publishedAt;
            $template->save();

            return [$version, $template];
        });

        Event::dispatch(new TemplatePublished(
            templateId: $template->id,
            versionId: $version->id,
            version: $version->version,
            channel: $version->channel,
        ));

        return $version;
    }
}
