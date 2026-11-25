<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Features\Catalog\Models\EventTemplate;
use AIArmada\Events\Features\Catalog\Models\EventTemplateItem;
use Illuminate\Support\Facades\DB;

final class DeleteTemplateDependents
{
    public function handle(EventTemplate $template): void
    {
        DB::transaction(function () use ($template): void {
            EventTemplateItem::query()
                ->where('event_template_id', $template->getKey())
                ->chunkById(200, function (iterable $items): void {
                    foreach ($items as $item) {
                        $item->delete();
                    }
                });
        });
    }
}
