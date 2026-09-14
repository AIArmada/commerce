<?php

declare(strict_types=1);

namespace AIArmada\Docs\Jobs;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Enums\EmailStatus;
use AIArmada\Docs\Mail\DocMail;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocEmail;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends a queued document email and transitions its delivery status.
 *
 * Skips already-sent mail so redeliveries are idempotent; failures are
 * recorded on the email row before the job is released for retry.
 */
final class SendDocEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public string $docEmailId,
        public string $docId,
        public bool $attachPdf = true,
    ) {
        $this->queue = (string) config('docs.email.queue', 'default');
    }

    public function handle(): void
    {
        $email = DocEmail::query()->withoutOwnerScope()->find($this->docEmailId);
        $doc = Doc::query()->withoutOwnerScope()->find($this->docId);

        if (! $email instanceof DocEmail || ! $doc instanceof Doc) {
            return;
        }

        OwnerContext::withOwner($email->owner, function () use ($email, $doc): void {
            $email->refresh();

            if (in_array($email->status, [EmailStatus::Sent, EmailStatus::Delivered], true)) {
                return;
            }

            try {
                Mail::send(new DocMail($email, $doc, $this->attachPdf));

                $email->update([
                    'status' => EmailStatus::Sent,
                    'sent_at' => CarbonImmutable::now(),
                ]);
            } catch (Throwable $exception) {
                $email->update([
                    'status' => EmailStatus::Failed,
                    'failed_at' => CarbonImmutable::now(),
                    'failure_reason' => Str::limit($exception->getMessage(), 2000),
                ]);

                throw $exception;
            }
        });
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['docs', 'email', "doc-email:{$this->docEmailId}"];
    }
}
