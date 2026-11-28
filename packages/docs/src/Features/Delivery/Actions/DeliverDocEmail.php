<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Enums\EmailStatus;
use AIArmada\Docs\Features\Delivery\Mail\DocMail;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocEmail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

final readonly class DeliverDocEmail
{
    public function execute(string $docEmailId, string $docId, bool $attachPdf): void
    {
        $email = DocEmail::query()->withoutOwnerScope()->find($docEmailId);
        $doc = Doc::query()->withoutOwnerScope()->find($docId);

        if (
            ! $email instanceof DocEmail || ! $doc instanceof Doc
            || (string) $email->doc_id !== (string) $doc->getKey()
            || $email->getAttribute('owner_type') !== $doc->getAttribute('owner_type')
            || (string) $email->getAttribute('owner_id') !== (string) $doc->getAttribute('owner_id')
        ) {
            return;
        }

        $owner = $email->owner;

        if ($email->hasOwner() && $owner === null) {
            return;
        }

        Cache::lock('docs.email.delivery.' . $docEmailId, 300)->block(10, function () use ($owner, $email, $doc, $attachPdf): void {
            OwnerContext::withOwner($owner, function () use ($email, $doc, $attachPdf): void {
                $email->refresh();

                if (in_array($email->status, [EmailStatus::Sent, EmailStatus::Delivered, EmailStatus::Bounced], true)) {
                    return;
                }

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
