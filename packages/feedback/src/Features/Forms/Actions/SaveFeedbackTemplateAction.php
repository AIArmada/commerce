<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Forms\Models\FeedbackTemplate;
use Illuminate\Support\Arr;

final readonly class SaveFeedbackTemplateAction
{
    public function execute(array $data, ?FeedbackTemplate $template = null): FeedbackTemplate
    {
        return (new FeedbackTemplate)->getConnection()->transaction(function () use ($data, $template): FeedbackTemplate {
            if ($template !== null) {
                if (FeedbackTemplate::ownerScopeConfig()->enabled) {
                    OwnerWriteGuard::findOrFailForOwner(FeedbackTemplate::class, $template->id);
                }

                $template = FeedbackTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            }

            $template ??= new FeedbackTemplate;
            $template->fill(Arr::only($data, ['name', 'slug', 'purpose', 'category', 'status', 'definition', 'settings', 'metadata']))->save();

            return $template;
        });
    }
}
