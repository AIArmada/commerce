<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Contracts\DocumentEmailSender;
use AIArmada\Docs\Enums\EmailStatus;
use AIArmada\Docs\Features\Delivery\EntryPoints\Jobs\SendDocEmailJob;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Features\Delivery\Models\DocEmail;
use AIArmada\Docs\Features\Delivery\Models\DocEmailTemplate;
use AIArmada\Docs\Features\Delivery\Queries\DueDocReminders;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Document email service for sending emails.
 */
final class DocEmailService implements DocumentEmailSender
{
    public function sendTo(
        string $docId,
        string $recipientEmail,
        ?string $recipientName = null,
        ?string $templateId = null,
        ?string $subject = null,
        ?string $body = null,
        array $metadata = [],
    ): string {
        $doc = config('docs.owner.enabled', false)
            ? OwnerWriteGuard::findOrFailForOwner(Doc::class, $docId)
            : Doc::query()->findOrFail($docId);

        return (string) $this->send(
            doc: $doc,
            recipientEmail: $recipientEmail,
            recipientName: $recipientName,
            template: $this->resolveTemplate($doc, $templateId),
            subjectOverride: $subject,
            bodyOverride: $body,
            metadata: $metadata,
        )->getKey();
    }

    /**
     * Send a document to a recipient.
     *
     * @param  array<string, mixed>  $variables  Additional template variables
     */
    public function send(
        Doc $doc,
        string $recipientEmail,
        ?string $recipientName = null,
        ?DocEmailTemplate $template = null,
        array $variables = [],
        ?string $subjectOverride = null,
        ?string $bodyOverride = null,
        array $metadata = [],
        ?string $trigger = null,
    ): DocEmail {
        $email = $this->createEmail($doc, $recipientEmail, $recipientName, $template, $variables, $subjectOverride, $bodyOverride, $metadata, $trigger);
        $this->queueEmail($email, (string) $doc->getKey());

        return $email;
    }

    /**
     * Reserve one scheduled reminder under the document lock. The eligibility
     * query is repeated after locking, so a stale batch cannot send twice or
     * use an outdated recipient.
     */
    public function sendScheduledReminder(string $docId, int $days, bool $overdue, DueDocReminders $queries): ?DocEmail
    {
        $email = (new Doc)->getConnection()->transaction(function () use ($docId, $days, $overdue, $queries): ?DocEmail {
            $doc = $queries->forCurrentOwner(false)->lockForUpdate()->find($docId);
            if (! $doc instanceof Doc) {
                return null;
            }

            $eligible = $overdue
                ? $queries->overdueQuery($days, false)
                : $queries->dueSoonQuery($days, false);

            if (! $eligible->whereKey($docId)->exists()) {
                return null;
            }

            $recipient = $doc->customer_data['email'] ?? null;
            if (! is_string($recipient) || filter_var(mb_trim($recipient), FILTER_VALIDATE_EMAIL) === false) {
                return null;
            }

            $name = $doc->customer_data['name'] ?? null;
            $name = ! $overdue && is_string($name) && mb_trim($name) !== '' ? $name : null;
            $variables = $overdue
                ? ['days_overdue' => $doc->due_date?->diffInDays(CarbonImmutable::now(), false)]
                : ['days_until_due' => CarbonImmutable::now()->diffInDays($doc->due_date, false)];

            return $this->createEmail(
                doc: $doc,
                recipientEmail: $recipient,
                recipientName: $name,
                variables: $variables,
                metadata: ['reminder_type' => $overdue ? 'overdue' : 'due_soon'],
                trigger: $overdue ? 'reminder' : 'due_soon',
            );
        });

        if ($email !== null) {
            $this->queueEmail($email, $docId);
        }

        return $email;
    }

    /** @param array<string, mixed> $variables
     * @param array<string, mixed> $metadata
     */
    private function createEmail(
        Doc $doc,
        string $recipientEmail,
        ?string $recipientName = null,
        ?DocEmailTemplate $template = null,
        array $variables = [],
        ?string $subjectOverride = null,
        ?string $bodyOverride = null,
        array $metadata = [],
        ?string $trigger = null,
    ): DocEmail {
        $recipientEmail = mb_trim($recipientEmail);
        $recipientName = $recipientName === null ? null : mb_trim($recipientName);
        $this->assertValidRecipient($recipientEmail, $recipientName);

        if (config('docs.owner.enabled', false)) {
            $doc = OwnerWriteGuard::findOrFailForOwner(
                Doc::class,
                (string) $doc->getKey(),
            );
        } else {
            $doc = $doc->newQuery()->findOrFail($doc->getKey());
        }

        $trigger ??= $template?->trigger ?? 'send';
        $template = $this->resolveTemplate($doc, $template === null ? null : (string) $template->getKey(), $trigger);

        // Build variables for template
        $templateVars = $this->buildVariables($doc, $variables);

        // Render subject and body
        $subject = $subjectOverride
            ?? $template?->renderSubject($templateVars)
            ?? $this->getDefaultSubject($doc);
        $body = $bodyOverride
            ?? $template?->renderBody($templateVars)
            ?? $this->getDefaultBody($doc);

        if (mb_trim($subject) === '' || mb_strlen($subject) > 255 || preg_match('/[\x00-\x1f\x7f]/', $subject) === 1) {
            throw new InvalidArgumentException('Document email subject must be nonempty, at most 255 characters, and contain no control characters.');
        }

        // Create email record
        return OwnerContext::withOwner(OwnerContext::fromTypeAndIdOrFail($doc->owner_type, $doc->owner_id), fn (): DocEmail => $doc->emails()->create([
            'doc_email_template_id' => $template?->id,
            'recipient_email' => $recipientEmail,
            'recipient_name' => $recipientName,
            'subject' => $subject,
            'body' => $body,
            'status' => EmailStatus::Queued,
            'metadata' => $metadata,
        ]));
    }

    /**
     * Send a reminder for an overdue document.
     */
    public function sendReminder(Doc $doc, string $recipientEmail, array $metadata = [], ?DocEmailTemplate $template = null): DocEmail
    {
        $template ??= $this->findTemplate($doc, 'reminder');

        return $this->send(
            doc: $doc,
            recipientEmail: $recipientEmail,
            template: $template,
            variables: [
                'days_overdue' => $doc->due_date?->diffInDays(CarbonImmutable::now(), false),
            ],
            metadata: array_merge(['reminder_type' => 'overdue'], $metadata),
            trigger: 'reminder',
        );
    }

    /**
     * Find a template for a document type and trigger.
     */
    public function findTemplate(Doc $doc, string $trigger): ?DocEmailTemplate
    {
        $query = $this->getTemplateQueryForDoc($doc)
            ->where('doc_type', $doc->doc_type)
            ->where('trigger', $trigger)
            ->where('is_active', true);

        if (config('docs.owner.enabled', false)) {
            $query->orderByRaw('CASE WHEN owner_id IS NULL THEN 1 ELSE 0 END');
        }

        return $query->orderBy('id')->first();
    }

    public function resolveTemplate(Doc $doc, ?string $templateId = null, string $trigger = 'send'): ?DocEmailTemplate
    {
        if ($templateId === null || $templateId === '') {
            return $this->findTemplate($doc, $trigger);
        }

        $template = $this->getTemplateQueryForDoc($doc)
            ->where('doc_type', $doc->doc_type)
            ->where('trigger', $trigger)
            ->where('is_active', true)
            ->find($templateId);

        if (! $template instanceof DocEmailTemplate) {
            throw ValidationException::withMessages([
                'template_id' => __('Invalid email template selection.'),
            ]);
        }

        return $template;
    }

    /**
     * @return Builder<DocEmailTemplate>
     */
    private function getTemplateQueryForDoc(Doc $doc): Builder
    {
        $query = DocEmailTemplate::query();

        if (! config('docs.owner.enabled', false)) {
            return $query;
        }

        return $query->forOwner(OwnerContext::fromTypeAndIdOrFail($doc->owner_type, $doc->owner_id), (bool) config('docs.owner.include_global', false));
    }

    /**
     * Build template variables from a document.
     *
     * @param  array<string, mixed>  $additional
     * @return array<string, mixed>
     */
    private function buildVariables(Doc $doc, array $additional = []): array
    {
        return array_merge([
            'doc_number' => $doc->doc_number,
            'doc_type' => $doc->doc_type,
            'issue_date' => $doc->issue_date->format('d/m/Y'),
            'due_date' => $doc->due_date?->format('d/m/Y') ?? '-',
            'total' => MoneyFormatter::decimalFromMinor($doc->total_minor, $doc->currency),
            'total_formatted' => MoneyFormatter::formatMinor($doc->total_minor, $doc->currency),
            'currency' => $doc->currency,
            'company_name' => $this->displayName($doc->company_data['name'] ?? null, $this->displayName(config('docs.company.name'), '')),
            'customer_name' => $this->displayName($doc->customer_data['name'] ?? null, 'Valued Customer'),
        ], $additional);
    }

    /**
     * Get default subject when no template exists.
     */
    private function getDefaultSubject(Doc $doc): string
    {
        $type = ucfirst(str_replace('_', ' ', $doc->doc_type));

        return "{$type} #{$doc->doc_number}";
    }

    /**
     * Get default body when no template exists.
     */
    private function getDefaultBody(Doc $doc): string
    {
        $type = ucfirst(str_replace('_', ' ', $doc->doc_type));
        $company = $this->displayName($doc->company_data['name'] ?? null, $this->displayName(config('docs.company.name'), ''));
        $formattedTotal = MoneyFormatter::formatMinor($doc->total_minor, $doc->currency);

        return "Dear Customer,\n\nPlease find attached {$type} #{$doc->doc_number}.\n\nTotal: {$formattedTotal}\n\nThank you for your business.\n\n{$company}";
    }

    /**
     * Queue an email for sending.
     */
    private function queueEmail(DocEmail $email, string $docId): void
    {
        if (config('docs.email.queue_enabled', true)) {
            SendDocEmailJob::dispatch(
                docEmailId: (string) $email->getKey(),
                docId: $docId,
                attachPdf: (bool) config('docs.email.attach_pdf', true),
            )->onQueue((string) config('docs.email.queue', 'default'));

            return;
        }

        $attachPdf = (bool) config('docs.email.attach_pdf', true);
        $email->getConnection()->afterCommit(static function () use ($email, $docId, $attachPdf): void {
            app(DeliverDocEmail::class)->execute((string) $email->getKey(), $docId, $attachPdf);
            $email->refresh();
        });
    }

    private function displayName(mixed $value, string $fallback): string
    {
        return is_string($value) && mb_trim($value) !== '' ? mb_trim($value) : $fallback;
    }

    private function assertValidRecipient(string $recipientEmail, ?string $recipientName): void
    {
        $email = mb_trim($recipientEmail);

        if ($email === '' || mb_strlen($email) > 320 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Document email recipient must be a valid email address.');
        }

        if ($recipientName !== null && ($recipientName === '' || mb_strlen($recipientName) > 255 || preg_match('/[\x00-\x1f\x7f]/', $recipientName) === 1)) {
            throw new InvalidArgumentException('Document email recipient name must be a non-empty string up to 255 characters.');
        }
    }
}
