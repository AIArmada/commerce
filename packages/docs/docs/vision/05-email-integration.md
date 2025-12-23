---
title: Email Integration
---

# Email Integration

> **Document:** 05 of 10  
> **Package:** `aiarmada/docs`  
> **Status:** Vision

---

## Overview

Build a comprehensive **email delivery system** for documents with automated sending, customizable templates, tracking, and reminder workflows.

---

## Email Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                     EMAIL PIPELINE                            │
├──────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐    │
│  │  Document   │────▶│  Template   │────▶│   Mailer    │    │
│  │   Ready     │     │  Renderer   │     │             │    │
│  └─────────────┘     └─────────────┘     └─────────────┘    │
│                                                  │            │
│                                                  ▼            │
│                                          ┌─────────────┐     │
│                                          │    SMTP     │     │
│                                          │   Queue     │     │
│                                          └─────────────┘     │
│                                                  │            │
│                        ┌─────────────────────────┼───────┐   │
│                        │                         │       │   │
│                        ▼                         ▼       ▼   │
│                 ┌───────────┐            ┌───────────┐       │
│                 │ Delivered │            │  Bounced  │       │
│                 │ • Track   │            │ • Retry   │       │
│                 │ • Open    │            │ • Alert   │       │
│                 └───────────┘            └───────────┘       │
│                                                               │
└──────────────────────────────────────────────────────────────┘
```

---

## Document Email Service

### DocumentEmailService

```php
class DocumentEmailService
{
    public function __construct(
        private EmailTemplateRenderer $renderer,
        private PdfGenerator $pdfGenerator,
        private EmailTracker $tracker,
    ) {}
    
    /**
     * Send document via email
     */
    public function send(Document $document, array $options = []): DocumentEmail
    {
        // Validate document can be sent
        $this->validateForSending($document);
        
        // Generate PDF
        $pdfPath = $this->pdfGenerator->generate($document);
        
        // Get recipient
        $recipient = $options['recipient'] ?? $document->customer_email;
        
        if (! $recipient) {
            throw new DocumentEmailException('No recipient email address');
        }
        
        // Create email record
        $email = DocumentEmail::create([
            'document_id' => $document->id,
            'recipient' => $recipient,
            'subject' => $this->getSubject($document, $options),
            'status' => 'queued',
            'tracking_id' => Str::uuid()->toString(),
        ]);
        
        // Queue email
        SendDocumentEmailJob::dispatch($email, $pdfPath, $options);
        
        // Update document status
        if ($document->status === DocumentStatus::Draft) {
            $document->update(['status' => DocumentStatus::Sent]);
        }
        
        return $email;
    }
    
    /**
     * Send with custom template
     */
    public function sendWithTemplate(
        Document $document,
        string $templateName,
        array $data = []
    ): DocumentEmail {
        $template = EmailTemplate::where('name', $templateName)->firstOrFail();
        
        return $this->send($document, [
            'template' => $template,
            'template_data' => $data,
        ]);
    }
    
    /**
     * Send reminder for overdue invoice
     */
    public function sendReminder(Document $document): DocumentEmail
    {
        if ($document->type !== DocumentType::Invoice || ! $document->isOverdue()) {
            throw new DocumentEmailException('Document is not an overdue invoice');
        }
        
        $reminderCount = $document->emails()->where('type', 'reminder')->count();
        
        return $this->send($document, [
            'template' => 'invoice-reminder',
            'type' => 'reminder',
            'template_data' => [
                'reminder_number' => $reminderCount + 1,
                'days_overdue' => now()->diffInDays($document->due_date),
            ],
        ]);
    }
    
    private function getSubject(Document $document, array $options): string
    {
        if (isset($options['subject'])) {
            return $options['subject'];
        }
        
        return match ($document->type) {
            DocumentType::Invoice => "Invoice {$document->number} from " . config('app.name'),
            DocumentType::Quotation => "Quotation {$document->number} from " . config('app.name'),
            DocumentType::CreditNote => "Credit Note {$document->number} from " . config('app.name'),
            DocumentType::Receipt => "Receipt {$document->number} from " . config('app.name'),
            default => "Document {$document->number} from " . config('app.name'),
        };
    }
}
```

---

## Email Templates

### EmailTemplate Model

```php
/**
 * @property string $id
 * @property string $name
 * @property string $code
 * @property DocumentType|null $document_type
 * @property string $subject_template
 * @property string $body_template
 * @property array|null $variables
 * @property bool $is_default
 * @property bool $is_active
 */
class EmailTemplate extends Model
{
    use HasUuids;
    
    protected $casts = [
        'document_type' => DocumentType::class,
        'variables' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];
    
    public function getTable(): string
    {
        return config('docs.database.tables.email_templates')
            ?? config('docs.database.table_prefix', 'doc_') . 'email_templates';
    }
}
```

### EmailTemplateRenderer

```php
class EmailTemplateRenderer
{
    /**
     * Render email template with document data
     */
    public function render(EmailTemplate $template, Document $document, array $extraData = []): RenderedEmail
    {
        $data = array_merge([
            'document' => $document,
            'document_number' => $document->number,
            'document_type' => $document->type->label(),
            'customer_name' => $document->customer_name,
            'issue_date' => $document->issue_date->format('d M Y'),
            'due_date' => $document->due_date?->format('d M Y'),
            'total' => Money::format($document->total_minor, $document->currency),
            'balance' => Money::format($document->getBalanceMinor(), $document->currency),
            'company_name' => config('docs.company.name'),
            'view_url' => $this->getViewUrl($document),
            'pay_url' => $this->getPayUrl($document),
        ], $extraData);
        
        $subject = $this->renderTemplate($template->subject_template, $data);
        $body = $this->renderTemplate($template->body_template, $data);
        
        return new RenderedEmail(
            subject: $subject,
            body: $body,
            plainText: $this->convertToPlainText($body),
        );
    }
    
    private function renderTemplate(string $template, array $data): string
    {
        // Replace {{ variable }} placeholders
        return preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            fn ($matches) => $data[$matches[1]] ?? $matches[0],
            $template
        );
    }
    
    private function getViewUrl(Document $document): string
    {
        return URL::signedRoute('docs.view', [
            'document' => $document->id,
        ], now()->addDays(30));
    }
    
    private function getPayUrl(Document $document): string
    {
        if ($document->type !== DocumentType::Invoice || $document->isPaid()) {
            return '';
        }
        
        return URL::signedRoute('docs.pay', [
            'document' => $document->id,
        ], now()->addDays(30));
    }
}
```

---

## Default Templates

### Invoice Template

```php
// Seeder for default templates
EmailTemplate::create([
    'name' => 'Invoice',
    'code' => 'invoice',
    'document_type' => DocumentType::Invoice,
    'subject_template' => 'Invoice {{ document_number }} from {{ company_name }}',
    'body_template' => <<<'HTML'
<p>Dear {{ customer_name }},</p>

<p>Please find attached invoice <strong>{{ document_number }}</strong> dated {{ issue_date }}.</p>

<table style="margin: 20px 0; border-collapse: collapse;">
    <tr>
        <td style="padding: 8px; border: 1px solid #ddd;">Total Amount:</td>
        <td style="padding: 8px; border: 1px solid #ddd;"><strong>{{ total }}</strong></td>
    </tr>
    <tr>
        <td style="padding: 8px; border: 1px solid #ddd;">Due Date:</td>
        <td style="padding: 8px; border: 1px solid #ddd;">{{ due_date }}</td>
    </tr>
</table>

<p>
    <a href="{{ view_url }}" style="display: inline-block; padding: 10px 20px; background: #3490dc; color: white; text-decoration: none; border-radius: 4px;">
        View Invoice
    </a>
    <a href="{{ pay_url }}" style="display: inline-block; padding: 10px 20px; background: #38c172; color: white; text-decoration: none; border-radius: 4px; margin-left: 10px;">
        Pay Now
    </a>
</p>

<p>Thank you for your business.</p>

<p>Best regards,<br>{{ company_name }}</p>
HTML,
    'is_default' => true,
    'is_active' => true,
]);
```

### Reminder Template

```php
EmailTemplate::create([
    'name' => 'Invoice Reminder',
    'code' => 'invoice-reminder',
    'document_type' => DocumentType::Invoice,
    'subject_template' => 'Reminder: Invoice {{ document_number }} is overdue',
    'body_template' => <<<'HTML'
<p>Dear {{ customer_name }},</p>

<p>This is a friendly reminder that invoice <strong>{{ document_number }}</strong> is now <strong>{{ days_overdue }} days overdue</strong>.</p>

<table style="margin: 20px 0; border-collapse: collapse;">
    <tr>
        <td style="padding: 8px; border: 1px solid #ddd;">Invoice Number:</td>
        <td style="padding: 8px; border: 1px solid #ddd;">{{ document_number }}</td>
    </tr>
    <tr>
        <td style="padding: 8px; border: 1px solid #ddd;">Original Due Date:</td>
        <td style="padding: 8px; border: 1px solid #ddd;">{{ due_date }}</td>
    </tr>
    <tr>
        <td style="padding: 8px; border: 1px solid #ddd;">Outstanding Balance:</td>
        <td style="padding: 8px; border: 1px solid #ddd;"><strong>{{ balance }}</strong></td>
    </tr>
</table>

<p>Please settle the outstanding amount at your earliest convenience.</p>

<p>
    <a href="{{ pay_url }}" style="display: inline-block; padding: 10px 20px; background: #e3342f; color: white; text-decoration: none; border-radius: 4px;">
        Pay Now
    </a>
</p>

<p>If you have already made payment, please disregard this reminder.</p>

<p>Best regards,<br>{{ company_name }}</p>
HTML,
    'is_default' => false,
    'is_active' => true,
]);
```

---

## Email Tracking

### DocumentEmail Model

```php
/**
 * @property string $id
 * @property string $document_id
 * @property string $recipient
 * @property string $subject
 * @property string $type
 * @property string $status
 * @property string $tracking_id
 * @property int $open_count
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $first_opened_at
 * @property Carbon|null $last_opened_at
 * @property Carbon|null $bounced_at
 * @property string|null $bounce_reason
 * @property array|null $metadata
 */
class DocumentEmail extends Model
{
    use HasUuids;
    
    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'first_opened_at' => 'datetime',
        'last_opened_at' => 'datetime',
        'bounced_at' => 'datetime',
        'metadata' => 'array',
    ];
    
    public function getTable(): string
    {
        return config('docs.database.tables.emails')
            ?? config('docs.database.table_prefix', 'doc_') . 'emails';
    }
    
    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
    
    public function markOpened(): void
    {
        $this->increment('open_count');
        
        $updates = ['last_opened_at' => now()];
        
        if (! $this->first_opened_at) {
            $updates['first_opened_at'] = now();
            
            // Update document status if applicable
            if ($this->document->status === DocumentStatus::Sent) {
                $this->document->update(['status' => DocumentStatus::Viewed]);
            }
        }
        
        $this->update($updates);
    }
    
    public function markBounced(string $reason): void
    {
        $this->update([
            'status' => 'bounced',
            'bounced_at' => now(),
            'bounce_reason' => $reason,
        ]);
    }
}
```

### EmailTracker

```php
class EmailTracker
{
    /**
     * Generate tracking pixel URL
     */
    public function getTrackingPixel(DocumentEmail $email): string
    {
        return route('docs.email.track', [
            'tracking_id' => $email->tracking_id,
        ]);
    }
    
    /**
     * Record email open
     */
    public function trackOpen(string $trackingId): void
    {
        $email = DocumentEmail::where('tracking_id', $trackingId)->first();
        
        if ($email) {
            $email->markOpened();
        }
    }
    
    /**
     * Generate 1x1 transparent pixel
     */
    public function generatePixel(): Response
    {
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        
        return response($pixel)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache');
    }
}
```

---

## Automated Reminders

### ReminderScheduler

```php
class ReminderScheduler
{
    public function __construct(
        private DocumentEmailService $emailService,
    ) {}
    
    /**
     * Schedule reminders for overdue invoices
     */
    public function scheduleReminders(): void
    {
        $config = config('docs.reminders');
        
        Document::query()
            ->where('type', DocumentType::Invoice)
            ->where('status', DocumentStatus::Overdue)
            ->whereColumn('paid_minor', '<', 'total_minor')
            ->where('due_date', '<', now())
            ->each(function (Document $invoice) use ($config) {
                $this->processReminder($invoice, $config);
            });
    }
    
    private function processReminder(Document $invoice, array $config): void
    {
        $daysOverdue = now()->diffInDays($invoice->due_date);
        $remindersSent = $invoice->emails()->where('type', 'reminder')->count();
        
        foreach ($config['schedule'] as $index => $days) {
            if ($daysOverdue >= $days && $remindersSent === $index) {
                // Check if max reminders reached
                if ($remindersSent >= $config['max_reminders']) {
                    return;
                }
                
                // Send reminder
                $this->emailService->sendReminder($invoice);
                
                // Fire event
                event(new ReminderSent($invoice, $remindersSent + 1));
                
                break;
            }
        }
    }
}
```

---

## Email Queue Job

### SendDocumentEmailJob

```php
class SendDocumentEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public int $tries = 3;
    public array $backoff = [60, 300, 900];
    
    public function __construct(
        public DocumentEmail $email,
        public string $pdfPath,
        public array $options = [],
    ) {}
    
    public function handle(EmailTemplateRenderer $renderer, EmailTracker $tracker): void
    {
        $document = $this->email->document;
        
        // Get template
        $template = $this->options['template'] 
            ?? EmailTemplate::where('document_type', $document->type)
                ->where('is_default', true)
                ->first();
        
        if (! $template) {
            throw new DocumentEmailException('No email template found');
        }
        
        // Render email
        $rendered = $renderer->render(
            $template,
            $document,
            $this->options['template_data'] ?? []
        );
        
        // Add tracking pixel
        $trackingPixel = $tracker->getTrackingPixel($this->email);
        $bodyWithTracking = $rendered->body . "<img src=\"{$trackingPixel}\" width=\"1\" height=\"1\" />";
        
        // Send email
        Mail::to($this->email->recipient)
            ->send(new DocumentMail(
                subject: $rendered->subject,
                body: $bodyWithTracking,
                attachments: [$this->pdfPath],
            ));
        
        // Update email record
        $this->email->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
    
    public function failed(Throwable $exception): void
    {
        $this->email->update([
            'status' => 'failed',
            'metadata' => array_merge($this->email->metadata ?? [], [
                'error' => $exception->getMessage(),
                'failed_at' => now()->toIso8601String(),
            ]),
        ]);
    }
}
```

---

## Database Schema

```php
// doc_email_templates table
Schema::create('doc_email_templates', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('code')->unique();
    $table->string('document_type')->nullable();
    $table->string('subject_template');
    $table->text('body_template');
    $table->json('variables')->nullable();
    $table->boolean('is_default')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    
    $table->index(['document_type', 'is_default']);
});

// doc_emails table
Schema::create('doc_emails', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('document_id');
    $table->string('recipient');
    $table->string('subject');
    $table->string('type')->default('send'); // send, reminder, follow_up
    $table->string('status')->default('queued'); // queued, sent, delivered, bounced, failed
    $table->string('tracking_id')->unique();
    $table->unsignedInteger('open_count')->default(0);
    $table->timestamp('sent_at')->nullable();
    $table->timestamp('delivered_at')->nullable();
    $table->timestamp('first_opened_at')->nullable();
    $table->timestamp('last_opened_at')->nullable();
    $table->timestamp('bounced_at')->nullable();
    $table->text('bounce_reason')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    $table->index('document_id');
    $table->index('status');
    $table->index('tracking_id');
});
```

---

## Configuration

```php
// config/docs.php
return [
    'email' => [
        'from_address' => env('DOCS_EMAIL_FROM', 'invoices@example.com'),
        'from_name' => env('DOCS_EMAIL_FROM_NAME', 'Invoicing'),
        'track_opens' => env('DOCS_EMAIL_TRACK_OPENS', true),
    ],
    
    'reminders' => [
        'enabled' => env('DOCS_REMINDERS_ENABLED', true),
        'schedule' => [7, 14, 21, 30], // Days overdue to send reminders
        'max_reminders' => 4,
    ],
];
```

---

## Navigation

**Previous:** [04-e-invoicing.md](04-e-invoicing.md)  
**Next:** [06-workflow-versioning.md](06-workflow-versioning.md)
