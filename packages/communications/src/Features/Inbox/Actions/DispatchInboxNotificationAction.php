<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Inbox\Actions;

use AIArmada\Communications\Contracts\InboxCommunicationCreator;
use AIArmada\Communications\Enums\NotificationFamily;
use AIArmada\Communications\Enums\NotificationPriority;
use AIArmada\Communications\Enums\NotificationTrigger;
use AIArmada\Communications\Features\Inbox\Models\NotificationInbox;
use AIArmada\Communications\Models\Communication;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

final class DispatchInboxNotificationAction
{
    public function __construct(
        private readonly ManageInboxEntries $inboxService,
        private readonly InboxCommunicationCreator $communicationCreator,
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
            $communication = Communication::query()->findOrFail($this->communicationCreator->create());

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
