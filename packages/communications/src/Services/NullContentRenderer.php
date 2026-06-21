<?php

declare(strict_types=1);

namespace AIArmada\Communications\Services;

use AIArmada\Communications\Contracts\ContentRenderer;
use AIArmada\Communications\Data\RenderedContentData;
use AIArmada\Communications\Models\CommunicationTemplate;

class NullContentRenderer implements ContentRenderer
{
    public function render(
        CommunicationTemplate $template,
        string $channel,
        ?string $locale,
        array $variables,
    ): RenderedContentData {
        return RenderedContentData::from([
            'channel' => $channel,
            'locale' => $locale,
        ]);
    }

    public function renderFromNotification(
        mixed $notifiable,
        mixed $notification,
        string $channel,
    ): RenderedContentData {
        $contentHtml = null;

        if (method_exists($notification, 'toMail') && $channel === 'mail') {
            $mail = $notification->toMail($notifiable);
            $contentHtml = $mail->render();
        }

        return RenderedContentData::from([
            'channel' => $channel,
            'subject' => method_exists($notification, 'toSubject') ? $notification->toSubject() : null,
            'contentHtml' => $contentHtml,
        ]);
    }
}
