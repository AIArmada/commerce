<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Features\Delivery\Models\DocEmailTemplate;
use AIArmada\Docs\Features\Delivery\Queries\DueDocReminders;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\States\Overdue;
use AIArmada\Docs\States\Pending;
use AIArmada\Docs\States\Sent;
use Carbon\CarbonImmutable;
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

            $this->sendRemindersForUpcomingDue($this->emailService, $this->dueDocReminders, $daysBeforeDue, false);
            $this->sendRemindersForOverdue($this->emailService, $this->dueDocReminders, $daysAfterOverdue, false);
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

    private function sendRemindersForUpcomingDue(DocEmailService $emailService, DueDocReminders $dueDocReminders, int $daysBeforeDue, bool $includeGlobal): void
    {
        $docs = $dueDocReminders->dueSoon($daysBeforeDue, includeGlobal: $includeGlobal);

        /** @var array<string, ?DocEmailTemplate> $templates */
        $templates = [];

        foreach ($docs as $doc) {
            $recipientEmail = $this->getRecipientEmail($doc);
            $recipientName = $this->getRecipientName($doc);

            if (! $recipientEmail || filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $templateKey = json_encode([$doc->owner_type, $doc->owner_id, $doc->doc_type], JSON_THROW_ON_ERROR);
            if (! array_key_exists($templateKey, $templates)) {
                $templates[$templateKey] = $emailService->findTemplate($doc, 'due_soon');
            }

            try {
                $emailService->send(
                    doc: $doc,
                    recipientEmail: $recipientEmail,
                    recipientName: $recipientName,
                    template: $templates[$templateKey],
                    variables: [
                        'days_until_due' => CarbonImmutable::now()->diffInDays($doc->due_date, false),
                    ],
                    metadata: ['reminder_type' => 'due_soon'],
                    trigger: 'due_soon',
                );

                Log::info('docs.reminder: Due soon reminder sent', [
                    'doc_id' => $doc->id,
                    'doc_number' => $doc->doc_number,
                    'days_until_due' => CarbonImmutable::now()->diffInDays($doc->due_date, false),
                ]);
            } catch (Throwable $e) {
                Log::error('docs.reminder: Failed to send due soon reminder', [
                    'doc_id' => $doc->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function sendRemindersForOverdue(DocEmailService $emailService, DueDocReminders $dueDocReminders, int $daysAfterOverdue, bool $includeGlobal): void
    {
        $docs = $dueDocReminders->overdue($daysAfterOverdue, includeGlobal: $includeGlobal);

        /** @var array<string, ?DocEmailTemplate> $templates */
        $templates = [];

        foreach ($docs as $doc) {
            $recipientEmail = $this->getRecipientEmail($doc);

            if (! $recipientEmail || filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $templateKey = json_encode([$doc->owner_type, $doc->owner_id, $doc->doc_type], JSON_THROW_ON_ERROR);
            if (! array_key_exists($templateKey, $templates)) {
                $templates[$templateKey] = $emailService->findTemplate($doc, 'reminder');
            }

            try {
                $emailService->sendReminder($doc, $recipientEmail, [], $templates[$templateKey]);

                Log::info('docs.reminder: Overdue reminder sent', [
                    'doc_id' => $doc->id,
                    'doc_number' => $doc->doc_number,
                    'days_overdue' => $doc->due_date?->diffInDays(CarbonImmutable::now(), false),
                ]);
            } catch (Throwable $e) {
                Log::error('docs.reminder: Failed to send overdue reminder', [
                    'doc_id' => $doc->id,
                    'error' => $e->getMessage(),
                ]);
            }
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

    private function getRecipientName(?Doc $doc): ?string
    {
        if (! $doc) {
            return null;
        }

        $customerData = $doc->customer_data;

        $value = is_array($customerData) ? ($customerData['name'] ?? null) : null;

        return is_string($value) && mb_trim($value) !== '' ? mb_trim($value) : null;
    }
}
