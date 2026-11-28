<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Templates\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Contracts\DocumentTemplates;
use AIArmada\Docs\Models\DocTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

final readonly class ManageDocTemplates implements DocumentTemplates
{
    public function create(array $data): string
    {
        $attributes = $this->validated($data);
        $default = (bool) ($attributes['is_default'] ?? false);
        $attributes['is_default'] = false;

        return $this->serialize(function () use ($attributes, $default): string {
            $template = DocTemplate::query()->create($attributes);

            if ($default) {
                $this->selectDefault($template);
            }

            return (string) $template->getKey();
        });
    }

    public function update(string $templateId, array $data): void
    {
        $attributes = $this->validated($data, partial: true);

        $this->serialize(function () use ($templateId, $attributes): void {
            $template = $this->lockTemplate($templateId);
            $default = (bool) ($attributes['is_default'] ?? $template->is_default);

            $template->update(array_merge($attributes, ['is_default' => false]));

            if ($default) {
                $this->selectDefault($template);
            }
        });
    }

    public function setDefault(string $templateId): void
    {
        $this->serialize(function () use ($templateId): void {
            $this->selectDefault($this->lockTemplate($templateId));
        });
    }

    public function remove(string $templateId): void
    {
        $this->serialize(function () use ($templateId): void {
            $this->lockTemplate($templateId)->delete();
        });
    }

    /** @template T
     * @param callable(): T $callback
     * @return T
     */
    private function serialize(callable $callback): mixed
    {
        $ownerKey = config('docs.owner.enabled', false)
            ? OwnerScopeKey::forOwner(OwnerContext::resolve())
            : OwnerScopeKey::GLOBAL;

        return Cache::lock('docs.templates.write.' . $ownerKey, 60)->block(10, fn (): mixed => (new DocTemplate)->getConnection()->transaction($callback));
    }

    private function lockTemplate(string $templateId): DocTemplate
    {
        if (config('docs.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(DocTemplate::class, $templateId);
        }

        return DocTemplate::query()->forOwner()->lockForUpdate()->findOrFail($templateId);
    }

    private function selectDefault(DocTemplate $template): void
    {
        $query = DocTemplate::query()
            ->where('doc_type', $template->doc_type)
            ->whereKeyNot($template->getKey())
            ->where('is_default', true);

        if (config('docs.owner.enabled', false)) {
            $query->withoutOwnerScope()
                ->where('owner_scope', $template->owner_scope);
        }

        $query->update(['is_default' => false]);
        $template->update(['is_default' => true]);
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
            'description' => ['sometimes', 'nullable', 'string'],
            'doc_type' => [...$required, 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'layout' => [...$required, 'array'],
            'settings' => ['sometimes', 'nullable', 'array'],
        ])->validate();
    }
}
