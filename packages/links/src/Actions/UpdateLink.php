<?php

declare(strict_types=1);

namespace AIArmada\Links\Actions;

use AIArmada\Links\Events\LinkUpdated;
use AIArmada\Links\Models\Link;
use AIArmada\Links\Persistence\LockOwnedLink;
use AIArmada\Links\Support\LinkAttributes;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

final class UpdateLink
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>|null  $allowedHosts
     */
    public function handle(Link $link, array $attributes, ?bool $requireHttps = null, ?array $allowedHosts = null): Link
    {
        $updated = $link->getConnection()->transaction(function () use ($link, $attributes, $requireHttps, $allowedHosts): Link {
            $link = app(LockOwnedLink::class)->handle($link);
            $validated = Validator::make($attributes, LinkAttributes::updateRules($link, $requireHttps, $allowedHosts))->validate();

            if (! is_string($validated['slug'] ?? null) || ($validated['slug'] ?? '') === '') {
                unset($validated['slug']);
            }

            $link->update($validated);

            return $link;
        });

        event(new LinkUpdated($updated));

        $link->setRawAttributes($updated->getAttributes(), true);

        return $updated;
    }
}
