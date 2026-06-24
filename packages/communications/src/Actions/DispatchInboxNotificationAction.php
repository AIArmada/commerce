<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\NotificationFamily;
use AIArmada\Communications\Enums\NotificationPriority;
use AIArmada\Communications\Enums\NotificationTrigger;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\NotificationInbox;
use AIArmada\Communications\Services\NotificationInboxService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

final class DispatchInboxNotificationAction
{
    public function __construct(
        private readonly NotificationInboxService $inboxService,
    ) {}

    public function handle(
        MorphMany | Model $recipient,
        string $title,
        string $body,
        NotificationFamily $family,
        NotificationPriority $priority,
        NotificationTrigger $trigger,
        ?array $data = null,
        ?CarbonInterface $scheduledAt = null,
    ): NotificationInbox {
        return DB::transaction(function () use ($recipient, $title, $body, $family, $priority, $trigger, $data, $scheduledAt): NotificationInbox {
            $communication = new Communication;
            $communication->direction = CommunicationDirection::Internal;
            $communication->category = CommunicationCategory::Internal;
            $communication->priority = CommunicationPriority::Normal;
            $communication->purpose = 'inbox_notification';
            $communication->status = CommunicationStatus::Completed;
            $communication->completed_at = CarbonImmutable::now();
            $communication->save();

            return $this->inboxService->create(
                recipient: $recipient,
                communication: $communication,
                family: $family,
                priority: $priority,
                trigger: $trigger,
                title: $title,
                body: $body,
                data: $data,
                scheduledAt: $scheduledAt,
            );
        });
    }
}
