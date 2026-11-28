<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Contracts\DocumentEmailTemplates;
use AIArmada\Docs\Features\Delivery\Models\DocEmailTemplate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final readonly class ManageDocEmailTemplates implements DocumentEmailTemplates
{
    public function create(array $data): string
    {
        $attributes = $this->validated($data);

        return (string) DocEmailTemplate::query()->create($attributes)->getKey();
    }

    public function update(string $templateId, array $data): void
    {
        (new DocEmailTemplate)->getConnection()->transaction(function () use ($templateId, $data): void {
            $template = $this->lockTemplate($templateId);
            $template->update($this->validated($data, partial: true));
        });
    }

    public function duplicate(string $templateId): string
    {
        return (new DocEmailTemplate)->getConnection()->transaction(function () use ($templateId): string {
            $template = $this->lockTemplate($templateId);
            $owner = OwnerContext::fromTypeAndIdOrFail($template->owner_type, $template->owner_id);

            return OwnerContext::withOwner($owner, function () use ($template): string {
                $copy = DocEmailTemplate::query()->create([
                    'name' => mb_substr($template->name, 0, 248) . ' (Copy)',
                    'slug' => mb_substr($template->slug, 0, 213) . '-copy-' . Str::uuid(),
                    'doc_type' => $template->doc_type,
                    'trigger' => $template->trigger,
                    'subject' => $template->subject,
                    'body' => $template->body,
                    'is_active' => $template->is_active,
                ]);

                return (string) $copy->getKey();
            });
        });
    }

    public function remove(array $templateIds): void
    {
        $templateIds = array_values(array_unique($templateIds));
        sort($templateIds, SORT_STRING);

        (new DocEmailTemplate)->getConnection()->transaction(function () use ($templateIds): void {
            foreach ($templateIds as $templateId) {
                $this->lockTemplate($templateId)->delete();
            }
        });
    }

    private function lockTemplate(string $templateId): DocEmailTemplate
    {
        if (config('docs.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(DocEmailTemplate::class, $templateId);
        }

        return DocEmailTemplate::query()->forOwner()->lockForUpdate()->findOrFail($templateId);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validated(array $data, bool $partial = false): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return Validator::make($data, [
            'name' => [...$required, 'string', 'max:255'],
            'slug' => [...$required, 'string', 'max:255'],
            'doc_type' => [...$required, 'string', 'max:255'],
            'trigger' => [...$required, Rule::in(['send', 'due_soon', 'reminder', 'overdue', 'paid', 'created'])],
            'subject' => [...$required, 'string', 'max:255'],
            'body' => [...$required, 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ])->validate();
    }
}
