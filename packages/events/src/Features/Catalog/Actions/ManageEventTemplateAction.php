<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Features\Catalog\Models\EventTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final readonly class ManageEventTemplateAction
{
    /** @param array<string, mixed> $data */
    public function save(?string $templateId, array $data, mixed $actor): EventTemplate
    {
        if (! $actor instanceof Authenticatable) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($templateId, $data, $actor): EventTemplate {
            if ($templateId !== null && EventTemplate::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(EventTemplate::class, $templateId);
            }

            $template = $templateId === null ? new EventTemplate : EventTemplate::query()->whereKey($templateId)->lockForUpdate()->firstOrFail();
            $subject = $templateId === null ? EventTemplate::class : $template;

            Gate::forUser($actor)->authorize($templateId === null ? 'create' : 'update', $subject);

            $attributes = Validator::make(Arr::only($data, ['name', 'code', 'description', 'template_type', 'status', 'visibility']), [
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'code' => ['sometimes', 'nullable', 'string', 'max:100'],
                'description' => ['sometimes', 'nullable', 'string'],
                'template_type' => ['sometimes', 'in:event,occurrence,session'],
                'status' => ['sometimes', 'in:draft,published,archived'],
                'visibility' => ['sometimes', 'in:public,private,internal'],
            ])->validate();

            if ($templateId === null) {
                $template->payload = [];
                $template->template_type = 'event';
                $template->status = 'draft';
                $template->visibility = 'private';
            } else {
                unset($attributes['template_type']);
            }

            $template->fill($attributes);

            if ($template->isDirty('status')) {
                if ($template->status === 'published') {
                    $template->published_at = CarbonImmutable::now();
                    $template->archived_at = null;
                } elseif ($template->status === 'archived') {
                    $template->archived_at = CarbonImmutable::now();
                } else {
                    $template->published_at = null;
                    $template->archived_at = null;
                }
            }

            $template->save();

            return $template;
        });
    }
}
