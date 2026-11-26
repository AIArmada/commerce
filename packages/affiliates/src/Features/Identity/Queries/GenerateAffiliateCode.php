<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Queries;

use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Generate a unique affiliate code.
 */
final class GenerateAffiliateCode
{
    /**
     * Generate a unique affiliate code based on the affiliate name.
     */
    public function handle(string $name = ''): string
    {
        $slug = Str::slug($name, '');
        $base = ($slug !== '' && mb_strlen($slug) > 0)
            ? Str::substr($slug, 0, 6)
            : 'AFF';

        // Ensure base is not empty after all transformations
        if ($base === '' || mb_strlen($base) === 0) {
            $base = 'AFF';
        }

        for ($attempt = 0; $attempt < 32; $attempt++) {
            $code = $base . Str::random(4);

            if (! Affiliate::query()->withoutOwnerScope()->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Unable to allocate an unused affiliate code.');
    }
}
