<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Templates\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Enums\TemplateStatus;
use AIArmada\Communications\Events\TemplatePublished;
use AIArmada\Communications\Features\Templates\Models\CommunicationTemplate;
use AIArmada\Communications\Features\Templates\Models\CommunicationTemplateVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use RuntimeException;

final class PublishTemplateAction
{
    public function handle(string $versionId): CommunicationTemplateVersion
    {
        [$version, $template] = (new CommunicationTemplateVersion)->getConnection()->transaction(function () use ($versionId): array {
            $version = CommunicationTemplateVersion::query()
                ->lockForUpdate()
                ->findOrFail($versionId);

            if (CommunicationTemplateVersion::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(CommunicationTemplateVersion::class, $versionId);
                OwnerWriteGuard::findOrFailForOwner(CommunicationTemplate::class, $version->template_id);
            }

            if ($version->published_at !== null) {
                throw new RuntimeException("Template version {$versionId} is already published.");
            }

            $template = CommunicationTemplate::query()
                ->lockForUpdate()
                ->findOrFail($version->template_id);

            if ($template->owner_type !== $version->owner_type || $template->owner_id !== $version->owner_id) {
                throw new RuntimeException('Template and version must share the same owner context.');
            }

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
