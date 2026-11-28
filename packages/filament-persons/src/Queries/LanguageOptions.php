<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Queries;

use AIArmada\CommerceSupport\Features\ReferenceData\Api\ReferenceDataApi;

final class LanguageOptions
{
    /** @var array<string, string>|null */
    private ?array $options = null;

    public function __construct(private readonly ReferenceDataApi $referenceData) {}

    /** @return array<string, string> */
    public function get(): array
    {
        return $this->options ??= $this->referenceData->languageOptions();
    }
}
