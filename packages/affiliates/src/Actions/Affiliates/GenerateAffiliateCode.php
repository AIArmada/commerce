<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Affiliates;

use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Generate a unique affiliate code.
 */
final class GenerateAffiliateCode
{
    use AsAction;

    /**
     * Generate a unique affiliate code based on the affiliate name.
     */
    public function handle(string $name = ''): string
    {
        $slug = Str::slug($name, '');
        $base = ($slug !== '' && mb_strlen($slug) > 0)
            ? Str::upper(Str::substr($slug, 0, 6))
            : 'AFF';

        // Ensure base is not empty after all transformations
        if ($base === '' || mb_strlen($base) === 0) {
            $base = 'AFF';
        }

        $suffix = Str::upper(Str::random(4));
        $code = $base . $suffix;

        while (Affiliate::where('code', $code)->exists()) {
            $suffix = Str::upper(Str::random(4));
            $code = $base . $suffix;
        }

        return $code;
    }
}
