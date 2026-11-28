<?php

declare(strict_types=1);

namespace AIArmada\Communications\Drivers\Notifications;

use AIArmada\Communications\Contracts\ContentRenderer;
use AIArmada\Communications\Data\RenderedContentData;

class NullContentRenderer implements ContentRenderer
{
    public function render(
        string $templateId,
        string $channel,
        ?string $locale,
        array $variables,
    ): RenderedContentData {
        return new RenderedContentData(channel: $channel, locale: $locale);
    }

    public function renderFromNotification(
        mixed $notifiable,
        mixed $notification,
        string $channel,
    ): RenderedContentData {
        $contentHtml = null;

        if (method_exists($notification, 'toMail') && $channel === 'mail') {
            $mail = $notification->toMail($notifiable);
            $contentHtml = (string) $mail->render();
        }

        return new RenderedContentData(
            channel: $channel,
            subject: method_exists($notification, 'toSubject') ? $notification->toSubject() : null,
            contentHtml: $contentHtml,
        );
    }
}
