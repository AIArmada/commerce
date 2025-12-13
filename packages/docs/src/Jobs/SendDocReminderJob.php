<?php

declare(strict_types=1);

namespace AIArmada\Docs\Jobs;

use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocEmailService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SendDocReminderJob implements ShouldQueue
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
    ) {}

    public function handle(DocEmailService $emailService): void
    {
        if ($this->docId !== null) {
            $this->sendReminderForDoc($emailService, $this->docId);

            return;
        }

        $this->sendRemindersForUpcomingDue($emailService);
        $this->sendRemindersForOverdue($emailService);
    }

    /**
     * @return array<string, mixed>
     */
    public function tags(): array
    {
        return [
            'docs',
            'reminder',
            $this->docId ? "doc:{$this->docId}" : 'batch',
        ];
    }

    protected function sendReminderForDoc(DocEmailService $emailService, string $docId): void
    {
        $doc = Doc::find($docId);
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
                'recipient' => $recipientEmail,
            ]);
        } catch (Exception $e) {
            Log::error('SendDocReminderJob: Failed to send reminder', [
                'doc_id' => $doc->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function sendRemindersForUpcomingDue(DocEmailService $emailService): void
    {
        $docs = $this->getDocsDueSoon();

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
                    template: $emailService->findTemplate($doc->doc_type, 'due_soon'),
                    variables: [
                        'days_until_due' => $doc->due_date?->diffInDays(now()),
                    ],
                );

                Log::info('SendDocReminderJob: Due soon reminder sent', [
                    'doc_id' => $doc->id,
                    'doc_number' => $doc->doc_number,
                    'days_until_due' => $doc->due_date?->diffInDays(now()),
                ]);
            } catch (Exception $e) {
                Log::error('SendDocReminderJob: Failed to send due soon reminder', [
                    'doc_id' => $doc->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function sendRemindersForOverdue(DocEmailService $emailService): void
    {
        $docs = $this->getOverdueDocs();

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
                    'days_overdue' => $doc->due_date?->diffInDays(now()),
                ]);
            } catch (Exception $e) {
                Log::error('SendDocReminderJob: Failed to send overdue reminder', [
                    'doc_id' => $doc->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return Collection<int, Doc>
     */
    protected function getDocsDueSoon(): Collection
    {
        $dueDate = now()->addDays($this->daysBeforeDue);

        return Doc::query()
            ->whereIn('status', [DocStatus::SENT, DocStatus::PENDING])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '=', $dueDate->toDateString())
            ->whereJsonContainsKey('customer_data->email')
            ->get();
    }

    /**
     * @return Collection<int, Doc>
     */
    protected function getOverdueDocs(): Collection
    {
        $overdueDate = now()->subDays($this->daysAfterOverdue);

        return Doc::query()
            ->where('status', DocStatus::OVERDUE)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '=', $overdueDate->toDateString())
            ->whereJsonContainsKey('customer_data->email')
            ->get();
    }

    protected function shouldSendReminder(Doc $doc): bool
    {
        $reminderStatuses = [
            DocStatus::DRAFT,
            DocStatus::PENDING,
            DocStatus::SENT,
            DocStatus::OVERDUE,
        ];

        return in_array($doc->status, $reminderStatuses, true);
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
