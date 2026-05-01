<?php

declare(strict_types=1);

namespace AIArmada\Docs\Mail;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocEmail;
use AIArmada\Docs\Services\DocEmailService;
use AIArmada\Docs\Services\DocService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mailable for sending documents via email.
 */
final class DocMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly DocEmail $docEmail,
        public readonly Doc $doc,
        public readonly bool $attachPdf = true,
    ) {
        $this->queue = config('docs.email.queue', 'default');
    }

    public function envelope(): Envelope
    {
        $fromAddress = config('docs.email.from_address') ?? config('mail.from.address') ?? 'noreply@example.com';
        $fromName = config('docs.email.from_name') ?? config('mail.from.name') ?? config('app.name');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            to: [
                new Address(
                    $this->docEmail->recipient_email,
                    $this->docEmail->recipient_name
                ),
            ],
            cc: $this->resolveCcAddresses(),
            subject: $this->docEmail->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'docs::emails.document',
            with: [
                'body' => $this->docEmail->body,
                'doc' => $this->doc,
                'docEmail' => $this->docEmail,
                'trackingPixelUrl' => $this->getTrackingPixelUrl(),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->attachPdf) {
            return [];
        }

        try {
            $docService = app(DocService::class);
            $owner = OwnerContext::fromTypeAndId($this->doc->owner_type, $this->doc->owner_id);

            $pdfPath = OwnerContext::withOwner($owner, fn (): string => $docService->generatePdf($this->doc, save: true));
            $disk = $docService->resolveStorageDiskForDocType($this->doc->doc_type);

            $docType = ucfirst(str_replace('_', '-', $this->doc->doc_type));

            return [
                Attachment::fromStorageDisk($disk, $pdfPath)
                    ->as("{$docType}-{$this->doc->doc_number}.pdf")
                    ->withMime('application/pdf'),
            ];
        } catch (Throwable $exception) {
            Log::warning('Failed to attach document PDF in queued mailable.', [
                'doc_id' => $this->doc->getKey(),
                'doc_number' => $this->doc->doc_number,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function getTrackingPixelUrl(): ?string
    {
        if (! config('docs.email.tracking.enabled', true)) {
            return null;
        }

        try {
            $emailService = app(DocEmailService::class);

            return $emailService->getTrackingPixelUrl($this->docEmail);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, Address>
     */
    private function resolveCcAddresses(): array
    {
        $metadata = $this->docEmail->metadata;

        if (! is_array($metadata)) {
            return [];
        }

        $cc = $metadata['cc'] ?? null;

        if (! is_string($cc) || $cc === '') {
            return [];
        }

        return [new Address($cc)];
    }
}
