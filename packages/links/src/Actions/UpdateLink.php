<?php

declare(strict_types=1);

namespace AIArmada\Links\Actions;

use AIArmada\Links\Events\LinkUpdated;
use AIArmada\Links\Models\Link;
use AIArmada\Links\Support\LinkAttributes;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

final class UpdateLink
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Link $link, array $attributes): Link
    {
        $validated = Validator::make($attributes, LinkAttributes::updateRules($link))->validate();

        if (! is_string($validated['slug'] ?? null) || ($validated['slug'] ?? '') === '') {
            unset($validated['slug']);
        }

        $link->update($validated);

        event(new LinkUpdated($link));

        return $link->refresh();
    }
}
