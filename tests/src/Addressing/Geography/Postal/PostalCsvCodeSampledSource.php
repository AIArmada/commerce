<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Addressing\Geography\Postal;

use AIArmada\Addressing\Contracts\PostalCodeSource;
use Illuminate\Support\LazyCollection;

/**
 * Restrict a postcode source to a code subset, keeping every link per code.
 *
 * Sampling by code (not by row) preserves per-code completeness, so the
 * exactly-one-primary contract stays meaningful on the sample.
 */
final class PostalCsvCodeSampledSource implements PostalCodeSource
{
    /**
     * @param  array<string, true>  $codes  Upper-cased code set to keep.
     */
    public function __construct(
        private readonly PostalCodeSource $inner,
        private readonly array $codes,
    ) {}

    public function key(): string
    {
        return $this->inner->key();
    }

    /** @return LazyCollection<int, PostalCodeData> */
    public function postalCodes(): LazyCollection
    {
        return $this->inner->postalCodes()->filter(
            fn ($item): bool => isset($this->codes[mb_strtoupper($item->code)])
        );
    }
}
