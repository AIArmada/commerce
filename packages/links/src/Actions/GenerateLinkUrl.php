<?php

declare(strict_types=1);

namespace AIArmada\Links\Actions;

use AIArmada\Links\Models\Link;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;
use Lorisleiva\Actions\Concerns\AsAction;

final class GenerateLinkUrl
{
    use AsAction;

    public function handle(Link $link): string
    {
        $name = (string) config('links.routing.name', 'links.redirect');

        if (! $link->require_signature) {
            return route($name, ['slug' => $link->slug]);
        }

        $ttl = (int) config('links.routing.signature_ttl_minutes', 60 * 24 * 30);

        return URL::temporarySignedRoute(
            $name,
            CarbonImmutable::now()->addMinutes(max(1, $ttl)),
            ['slug' => $link->slug],
        );
    }
}
