<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Features\Delivery\Queries\DueDocReminders;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\States\Overdue;
use AIArmada\Docs\States\Pending;
use AIArmada\Docs\States\Sent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final readonly class DeliverDocReminders
{
    public function __construct(
        private DocEmailService $emailService,
        private DueDocReminders $dueDocReminders,
    ) {}

    public function execute(?string $docId, int $daysBeforeDue, int $daysAfterOverdue, ?string $ownerType, string | int | null $ownerId): void
    {
        $owner = OwnerContext::fromTypeAndIdOrFail($ownerType, $ownerId);

        OwnerContext::withOwner($owner, function () use ($docId, $daysBeforeDue, $daysAfterOverdue): void {
            if ($docId !== null) {
                $this->sendReminderForDoc($this->emailService, $this->dueDocReminders, $docId);

                return;
            }

            $this->sendRemindersForUpcomingDue($daysBeforeDue);
            $this->sendRemindersForOverdue($daysAfterOverdue);
        });
    }

    private function sendReminderForDoc(DocEmailService $emailService, DueDocReminders $dueDocReminders, string $docId): void
    {
        $doc = Str::isUuid($docId) ? $dueDocReminders->forCurrentOwner(false)->find($docId) : null;
        $recipientEmail = $this->getRecipientEmail($doc);

        if (! $doc || ! $recipientEmail) {
            Log::warning('docs.reminder: Document not found or has no recipient email', [
                'doc_id' => $docId,
            ]);

            return;
        }

        if (filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
            Log::warning('docs.reminder: Document has an invalid recipient email', [
                'doc_id' => $docId,
            ]);

            return;
        }

        if (! $this->shouldSendReminder($doc)) {
            return;
        }

        try {
            $emailService->sendReminder($doc, $recipientEmail);

            Log::info('docs.reminder: Reminder sent', [
                'doc_id' => $doc->id,
                'doc_number' => $doc->doc_number,
            ]);
        } catch (Throwable $e) {
            Log::error('docs.reminder: Failed to send reminder', [
                'doc_id' => $doc->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function sendRemindersForUpcomingDue(int $daysBeforeDue): void
    {
        foreach ($this->dueDocReminders->dueSoon($daysBeforeDue, includeGlobal: false) as $doc) {
            $this->sendScheduledReminder($doc, $daysBeforeDue, false);
        }
    }

    private function sendRemindersForOverdue(int $daysAfterOverdue): void
    {
        foreach ($this->dueDocReminders->overdue($daysAfterOverdue, includeGlobal: false) as $doc) {
            $this->sendScheduledReminder($doc, $daysAfterOverdue, true);
        }
    }

    private function sendScheduledReminder(Doc $doc, int $days, bool $overdue): void
    {
        try {
            if ($this->emailService->sendScheduledReminder((string) $doc->getKey(), $days, $overdue, $this->dueDocReminders) !== null) {
                Log::info('docs.reminder: Scheduled reminder sent', ['doc_id' => $doc->id]);
            }
        } catch (Throwable $exception) {
            Log::error('docs.reminder: Failed to send scheduled reminder', [
                'doc_id' => $doc->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function shouldSendReminder(Doc $doc): bool
    {
        return $doc->status->equals(Draft::class)
            || $doc->status->equals(Pending::class)
            || $doc->status->equals(Sent::class)
            || $doc->status->equals(Overdue::class);
    }

    private function getRecipientEmail(?Doc $doc): ?string
    {
        if (! $doc) {
            return null;
        }

        $customerData = $doc->customer_data;

        $value = is_array($customerData) ? ($customerData['email'] ?? null) : null;

        return is_string($value) && mb_trim($value) !== '' ? mb_trim($value) : null;
    }

}
