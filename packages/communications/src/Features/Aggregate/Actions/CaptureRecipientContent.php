<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Contracts\ContentRenderer;
use AIArmada\Communications\Contracts\NotificationContentCapture;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Contracts\RecipientSnapshotResolver;
use AIArmada\Communications\Data\CapturedRecipientContentData;
use AIArmada\Communications\Enums\RecipientRole;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationContent;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationRecipient;
use AIArmada\Communications\Models\Communication;
use Carbon\CarbonImmutable;

final readonly class CaptureRecipientContent implements NotificationContentCapture
{
    public function __construct(
        private RecipientSnapshotResolver $recipients,
        private ContentRenderer $renderer,
        private PayloadRedactor $redactor,
    ) {}

    public function recipient(string $communicationId, mixed $notifiable): string
    {
        $model = new Communication;

        return $model->getConnection()->transaction(function () use ($model, $communicationId, $notifiable): string {
            if (Communication::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(Communication::class, $communicationId);
            }

            $communication = $model->newQuery()->whereKey($communicationId)->lockForUpdate()->firstOrFail();
            $type = is_object($notifiable)
                ? (method_exists($notifiable, 'getMorphClass') ? $notifiable->getMorphClass() : $notifiable::class)
                : null;
            $id = is_object($notifiable) && method_exists($notifiable, 'getKey') ? (string) $notifiable->getKey() : null;

            $recipient = CommunicationRecipient::query()
                ->where('communication_id', $communication->id)
                ->where('recipient_type', $type)
                ->where('recipient_id', $id)
                ->where('role', RecipientRole::To->value)
                ->first();

            if ($recipient === null) {
                $snapshot = $this->recipients->resolve($notifiable);
                $recipient = new CommunicationRecipient;
                $recipient->communication_id = $communication->id;
                $recipient->recipient_type = $type;
                $recipient->recipient_id = $id;
                $recipient->role = RecipientRole::To;
                $recipient->display_name = $snapshot->displayName;
                $recipient->locale = $snapshot->locale;
                $recipient->timezone = $snapshot->timezone;
                $recipient->snapshot = $this->redactor->redact($snapshot->extra);
                $recipient->save();
            }

            return $recipient->id;
        });
    }

    public function capture(string $communicationId, mixed $notifiable, object $notification, string $channel): CapturedRecipientContentData
    {
        $model = new Communication;

        return $model->getConnection()->transaction(function () use ($communicationId, $notifiable, $notification, $channel): CapturedRecipientContentData {
            $recipientId = $this->recipient($communicationId, $notifiable);
            $rendered = $this->renderer->renderFromNotification($notifiable, $notification, $channel);
            $content = new CommunicationContent;
            $content->communication_id = $communicationId;
            $content->recipient_id = $recipientId;
            $content->channel = $channel;
            $content->locale = $rendered->locale;
            $content->subject = $this->redactor->redactText($rendered->subject);
            $content->content_text = $this->redactor->redactText($rendered->contentText);
            $content->content_html = $this->redactor->redactText($rendered->contentHtml);
            $content->payload = $this->redactor->redact($rendered->payload);
            $content->checksum = $rendered->checksum;
            $content->rendered_at = CarbonImmutable::now();
            $content->save();

            return new CapturedRecipientContentData($recipientId, $content->id);
        });
    }
}
