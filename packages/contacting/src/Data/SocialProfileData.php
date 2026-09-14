<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Data;

use AIArmada\Contacting\Enums\SocialPlatform;
use DateTimeInterface;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapInputName(SnakeCaseMapper::class)]
final class SocialProfileData extends Data
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly string $platform = '',
        public readonly string | Optional $purpose = new Optional,
        public readonly string | null | Optional $label = new Optional,
        public readonly string | null | Optional $handle = new Optional,
        public readonly string | null | Optional $url = new Optional,
        public readonly string | null | Optional $displayName = new Optional,
        public readonly string | null | Optional $externalId = new Optional,
        public readonly bool | Optional $isPrimary = new Optional,
        public readonly ?bool $isPublic = null,
        public readonly bool | Optional $isVerified = new Optional,
        public readonly mixed $verifiedAt = null,
        public readonly array | Optional $metadata = new Optional,
        public readonly DateTimeInterface | string | null $validFrom = null,
        public readonly DateTimeInterface | string | null $validUntil = null,
        public readonly ?int $sortOrder = null,
    ) {}

    /**
     * Rules run against the validated payload, so absent (Optional) values are
     * skipped automatically.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        $purposeValues = self::configValues('contacting.contact_methods.purposes');
        $strictPlatforms = (bool) config('contacting.features.strict_social_platforms', false);

        return [
            'platform' => array_values(array_filter([
                'required',
                'string',
                'max:255',
                $strictPlatforms ? Rule::in(self::platformValues()) : null,
            ])),
            'purpose' => array_values(array_filter([
                'string',
                'max:255',
                $purposeValues !== [] ? Rule::in($purposeValues) : null,
            ])),
            'label' => ['nullable', 'string', 'max:255'],
            'handle' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string'],
            'displayName' => ['nullable', 'string', 'max:255'],
            'externalId' => ['nullable', 'string', 'max:255'],
            'isPrimary' => ['boolean'],
            'isPublic' => ['nullable', 'boolean'],
            'isVerified' => ['boolean'],
            'verifiedAt' => ['nullable', 'date'],
            'metadata' => ['array'],
            'validFrom' => ['nullable', 'date'],
            'validUntil' => ['nullable', 'date'],
            'sortOrder' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return list<string>
     */
    private static function configValues(string $key): array
    {
        $values = config($key, []);

        if (! is_array($values)) {
            return [];
        }

        $values = array_is_list($values) ? $values : array_keys($values);

        return array_values(array_filter($values, is_string(...)));
    }

    /**
     * @return list<string>
     */
    private static function platformValues(): array
    {
        return array_map(
            static fn (SocialPlatform $platform): string => $platform->value,
            SocialPlatform::cases(),
        );
    }
}
