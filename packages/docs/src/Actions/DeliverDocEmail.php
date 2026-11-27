<?php

declare(strict_types=1);

namespace AIArmada\Docs\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Enums\EmailStatus;
use AIArmada\Docs\Mail\DocMail;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocEmail;
use Carbon\CarbonImmutable;
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

        OwnerContext::withOwner($owner, function () use ($email, $doc, $attachPdf): void {
            $email->refresh();

            if (in_array($email->status, [EmailStatus::Sent, EmailStatus::Delivered], true)) {
                return;
            }

            try {
                Mail::send(new DocMail($email, $doc, $attachPdf));

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
}
