<?php

declare(strict_types=1);

namespace AIArmada\Links\Actions;

use AIArmada\Links\Events\LinkCreated;
use AIArmada\Links\Models\Link;
use AIArmada\Links\Support\LinkAttributes;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

final class CreateLink
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): Link
    {
        $validated = Validator::make($attributes, LinkAttributes::creationRules())->validate();

        $link = Link::query()->create($validated);

        event(new LinkCreated($link));

        return $link;
    }
}
