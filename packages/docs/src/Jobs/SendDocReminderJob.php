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

        if (! $doc || ! $doc->recipient_email) {
            Log::warning('SendDocReminderJob: Document not found or has no recipient email', [
                'doc_id' => $docId,
            ]);

            return;
        }

        if (! $this->shouldSendReminder($doc)) {
            return;
        }

        try {
            $emailService->sendReminder($doc, $doc->recipient_email);

            Log::info('SendDocReminderJob: Reminder sent', [
                'doc_id' => $doc->id,
                'doc_number' => $doc->document_number,
                'recipient' => $doc->recipient_email,
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
            if (! $doc->recipient_email) {
                continue;
            }

            try {
                $emailService->send(
                    doc: $doc,
                    recipientEmail: $doc->recipient_email,
                    recipientName: $doc->recipient_name,
                    template: $emailService->findTemplate($doc->type->value, 'due_soon'),
                    variables: [
                        'days_until_due' => $doc->due_date?->diffInDays(now()),
                    ],
                );

                Log::info('SendDocReminderJob: Due soon reminder sent', [
                    'doc_id' => $doc->id,
                    'doc_number' => $doc->document_number,
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
            if (! $doc->recipient_email) {
                continue;
            }

            try {
                $emailService->sendReminder($doc, $doc->recipient_email);

                Log::info('SendDocReminderJob: Overdue reminder sent', [
                    'doc_id' => $doc->id,
                    'doc_number' => $doc->document_number,
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
            ->whereIn('status', [DocStatus::Sent, DocStatus::Pending])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '=', $dueDate->toDateString())
            ->whereNotNull('recipient_email')
            ->get();
    }

    /**
     * @return Collection<int, Doc>
     */
    protected function getOverdueDocs(): Collection
    {
        $overdueDate = now()->subDays($this->daysAfterOverdue);

        return Doc::query()
            ->where('status', DocStatus::Overdue)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '=', $overdueDate->toDateString())
            ->whereNotNull('recipient_email')
            ->get();
    }

    protected function shouldSendReminder(Doc $doc): bool
    {
        $reminderStatuses = [
            DocStatus::Draft,
            DocStatus::Pending,
            DocStatus::Sent,
            DocStatus::Overdue,
        ];

        return in_array($doc->status, $reminderStatuses, true);
    }
}
