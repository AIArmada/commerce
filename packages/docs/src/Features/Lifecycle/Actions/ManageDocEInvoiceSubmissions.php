<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Lifecycle\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Contracts\DocumentEInvoiceSubmissions;
use AIArmada\Docs\Enums\DocEInvoiceSubmissionStatus;
use AIArmada\Docs\Enums\DocEInvoiceValidationStatus;
use AIArmada\Docs\Features\Lifecycle\Models\DocEInvoiceSubmission;
use AIArmada\Docs\Models\Doc;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class ManageDocEInvoiceSubmissions implements DocumentEInvoiceSubmissions
{
    public function record(string $docId, string $submissionUid, array $data = []): string
    {
        $submissionUid = mb_trim($submissionUid);
        Validator::make(['submission_uid' => $submissionUid], [
            'submission_uid' => ['required', 'string', 'max:255'],
        ])->validate();

        $attributes = $this->validated($data);

        return (new Doc)->getConnection()->transaction(function () use ($docId, $submissionUid, $attributes): string {
            $doc = $this->lockDocument($docId);
            $owner = OwnerContext::fromTypeAndIdOrFail($doc->owner_type, $doc->owner_id);

            return OwnerContext::withOwner($owner, function () use ($doc, $submissionUid, $attributes): string {
                $existing = DocEInvoiceSubmission::query()->withoutOwnerScope()
                    ->where('doc_id', $doc->getKey())
                    ->where('submission_uid', $submissionUid)
                    ->first();

                if ($existing instanceof DocEInvoiceSubmission) {
                    $this->assertOwner($doc, $existing);

                    return (string) $existing->getKey();
                }

                $submission = $doc->eInvoiceSubmission()->make([
                    ...$attributes,
                    'submission_uid' => $submissionUid,
                ]);
                $submission->forceFill(['owner_type' => $doc->owner_type, 'owner_id' => $doc->owner_id]);
                $submission->save();

                return (string) $submission->getKey();
            });
        });
    }

    public function update(string $docId, string $submissionId, array $data): void
    {
        $attributes = $this->validated($data);

        (new Doc)->getConnection()->transaction(function () use ($docId, $submissionId, $attributes): void {
            $doc = $this->lockDocument($docId);
            $owner = OwnerContext::fromTypeAndIdOrFail($doc->owner_type, $doc->owner_id);

            OwnerContext::withOwner($owner, function () use ($doc, $submissionId, $attributes): void {
                $submission = DocEInvoiceSubmission::query()->withoutOwnerScope()
                    ->where('doc_id', $doc->getKey())
                    ->lockForUpdate()
                    ->findOrFail($submissionId);

                $this->assertOwner($doc, $submission);
                $submission->update($attributes);
            });
        });
    }

    private function lockDocument(string $docId): Doc
    {
        if (config('docs.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(Doc::class, $docId);
        }

        return Doc::query()->forOwner()->lockForUpdate()->findOrFail($docId);
    }

    private function assertOwner(Doc $doc, DocEInvoiceSubmission $submission): void
    {
        if ($submission->owner_type !== $doc->owner_type || (string) $submission->owner_id !== (string) $doc->owner_id) {
            throw new LogicException('Document e-invoice submission ownership is inconsistent.');
        }
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validated(array $data): array
    {
        foreach (['id', 'doc_id', 'submission_uid', 'owner_type', 'owner_id'] as $field) {
            if (array_key_exists($field, $data)) {
                throw ValidationException::withMessages([$field => 'Submission identity cannot be supplied or changed.']);
            }
        }

        return Validator::make($data, [
            'document_uuid' => ['sometimes', 'nullable', 'string', 'max:255'],
            'long_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::enum(DocEInvoiceSubmissionStatus::class)],
            'validation_status' => ['sometimes', 'nullable', Rule::enum(DocEInvoiceValidationStatus::class)],
            'errors' => ['sometimes', 'nullable', 'array'],
            'warnings' => ['sometimes', 'nullable', 'array'],
            'ubl_xml' => ['sometimes', 'nullable', 'string'],
            'qr_code_url' => ['sometimes', 'nullable', 'string', 'max:255', 'url:http,https'],
            'submitted_at' => ['sometimes', 'nullable', 'date'],
            'validated_at' => ['sometimes', 'nullable', 'date'],
            'completed_at' => ['sometimes', 'nullable', 'date'],
            'failed_at' => ['sometimes', 'nullable', 'date'],
        ])->validate();
    }
}
