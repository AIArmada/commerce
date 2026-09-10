<?php

declare(strict_types=1);

namespace AIArmada\Docs\Jobs;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocEmailService;
use AIArmada\Docs\Services\DueDocReminders;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\States\Overdue;
use AIArmada\Docs\States\Pending;
use AIArmada\Docs\States\Sent;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SendDocReminderJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public ?string $docId = null,
        public int $daysBeforeDue = 3,
        public int $daysAfterOverdue = 1,
        public ?string $ownerType = null,
        public string | int | null $ownerId = null,
    ) {}

    public function handle(DocEmailService $emailService, ?DueDocReminders $dueDocReminders = null): void
    {
        $dueDocReminders ??= app(DueDocReminders::class);

        if ($this->shouldFanOutByOwner()) {
            $this->dispatchPerOwner($dueDocReminders);

            return;
        }

        $owner = OwnerContext::fromTypeAndId($this->ownerType, $this->ownerId);

        OwnerContext::withOwner($owner, function () use ($dueDocReminders, $emailService): void {
            if ($this->docId !== null) {
                $this->sendReminderForDoc($emailService, $dueDocReminders, $this->docId);

                return;
            }

            $this->sendRemindersForUpcomingDue($emailService, $dueDocReminders);
            $this->sendRemindersForOverdue($emailService, $dueDocReminders);
        });
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'docs',
            'reminder',
            $this->docId ? "doc:{$this->docId}" : 'batch',
        ];
    }

    protected function sendReminderForDoc(DocEmailService $emailService, DueDocReminders $dueDocReminders, string $docId): void
    {
        $doc = $dueDocReminders->forCurrentOwner()->find($docId);
        $recipientEmail = $this->getRecipientEmail($doc);

        if (! $doc || ! $recipientEmail) {
            Log::warning('SendDocReminderJob: Document not found or has no recipient email', [
                'doc_id' => $docId,
            ]);

            return;
        }

        if (! $this->shouldSendReminder($doc)) {
            return;
        }

        try {
            $emailService->sendReminder($doc, $recipientEmail);

            Log::info('SendDocReminderJob: Reminder sent', [
                'doc_id' => $doc->id,
                'doc_number' => $doc->doc_number,
            ]);
        } catch (Throwable $e) {
            Log::error('SendDocReminderJob: Failed to send reminder', [
                'doc_id' => $doc->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function sendRemindersForUpcomingDue(DocEmailService $emailService, DueDocReminders $dueDocReminders): void
    {
        $docs = $dueDocReminders->dueSoon($this->daysBeforeDue);

        foreach ($docs as $doc) {
            $recipientEmail = $this->getRecipientEmail($doc);
            $recipientName = $this->getRecipientName($doc);

            if (! $recipientEmail) {
                continue;
            }

            try {
                $emailService->send(
                    doc: $doc,
                    recipientEmail: $recipientEmail,
                    recipientName: $recipientName,
                    template: $emailService->findTemplate($doc, 'due_soon'),
                    variables: [
                        'days_until_due' => CarbonImmutable::now()->diffInDays($doc->due_date, false),
                    ],
                    metadata: ['reminder_type' => 'due_soon'],
                );

                Log::info('SendDocReminderJob: Due soon reminder sent', [
                    'doc_id' => $doc->id,
                    'doc_number' => $doc->doc_number,
                    'days_until_due' => CarbonImmutable::now()->diffInDays($doc->due_date, false),
                ]);
            } catch (Throwable $e) {
                Log::error('SendDocReminderJob: Failed to send due soon reminder', [
                    'doc_id' => $doc->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function sendRemindersForOverdue(DocEmailService $emailService, DueDocReminders $dueDocReminders): void
    {
        $docs = $dueDocReminders->overdue($this->daysAfterOverdue);

        foreach ($docs as $doc) {
            $recipientEmail = $this->getRecipientEmail($doc);

            if (! $recipientEmail) {
                continue;
            }

            try {
                $emailService->sendReminder($doc, $recipientEmail);

                Log::info('SendDocReminderJob: Overdue reminder sent', [
                    'doc_id' => $doc->id,
                    'doc_number' => $doc->doc_number,
                    'days_overdue' => $doc->due_date?->diffInDays(CarbonImmutable::now(), false),
                ]);
            } catch (Throwable $e) {
                Log::error('SendDocReminderJob: Failed to send overdue reminder', [
                    'doc_id' => $doc->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function shouldFanOutByOwner(): bool
    {
        if ($this->docId !== null) {
            return false;
        }

        if (! config('docs.owner.enabled', false)) {
            return false;
        }

        return $this->ownerType === null && $this->ownerId === null;
    }

    private function dispatchPerOwner(DueDocReminders $dueDocReminders): void
    {
        $owners = $dueDocReminders->ownerTuples();

        if ($owners->isEmpty()) {
            return;
        }

        $dispatched = [];

        foreach ($owners as $ownerTuple) {
            $ownerType = $ownerTuple->owner_type;
            $ownerId = $ownerTuple->owner_id;
            $key = ($ownerType ?? 'global') . '|' . ($ownerId ?? 'global');

            if (isset($dispatched[$key])) {
                continue;
            }

            $dispatched[$key] = true;

            self::dispatch(
                docId: null,
                daysBeforeDue: $this->daysBeforeDue,
                daysAfterOverdue: $this->daysAfterOverdue,
                ownerType: $ownerType,
                ownerId: $ownerId,
            );
        }
    }

    protected function shouldSendReminder(Doc $doc): bool
    {
        return $doc->status->equals(Draft::class)
            || $doc->status->equals(Pending::class)
            || $doc->status->equals(Sent::class)
            || $doc->status->equals(Overdue::class);
    }

    protected function getRecipientEmail(?Doc $doc): ?string
    {
        if (! $doc) {
            return null;
        }

        $customerData = $doc->customer_data;

        return is_array($customerData) ? ($customerData['email'] ?? null) : null;
    }

    protected function getRecipientName(?Doc $doc): ?string
    {
        if (! $doc) {
            return null;
        }

        $customerData = $doc->customer_data;

        return is_array($customerData) ? ($customerData['name'] ?? null) : null;
    }
}
