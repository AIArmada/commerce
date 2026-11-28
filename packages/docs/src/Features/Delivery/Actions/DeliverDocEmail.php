<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Enums\EmailStatus;
use AIArmada\Docs\Features\Delivery\Mail\DocMail;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocEmail;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

final readonly class DeliverDocEmail
{
    public function execute(string $docEmailId, string $docId, bool $attachPdf): void
    {
        Cache::lock('docs.email.delivery.' . $docEmailId, 300)->block(10, function () use ($docEmailId, $docId, $attachPdf): void {
            $email = DocEmail::query()->withoutOwnerScope()->find($docEmailId);

            if (! $email instanceof DocEmail
                || (string) $email->doc_id !== $docId
                || in_array($email->status, [EmailStatus::Sent, EmailStatus::Delivered, EmailStatus::Bounced], true)) {
                return;
            }

            $doc = Doc::query()->withoutOwnerScope()->find($docId);

            if (! $doc instanceof Doc
                || $email->owner_type !== $doc->owner_type
                || (string) $email->owner_id !== (string) $doc->owner_id) {
                return;
            }

            try {
                $owner = OwnerContext::fromTypeAndIdOrFail($email->owner_type, $email->owner_id);
            } catch (InvalidArgumentException | ModelNotFoundException) {
                return;
            }

            OwnerContext::withOwner($owner, function () use ($email, $doc, $attachPdf): void {
                try {
                    Mail::send(new DocMail($email, $doc, $attachPdf));

                    $email->update([
                        'status' => EmailStatus::Sent,
                        'sent_at' => CarbonImmutable::now(),
                        'failed_at' => null,
                        'failure_reason' => null,
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
        });
    }
}
