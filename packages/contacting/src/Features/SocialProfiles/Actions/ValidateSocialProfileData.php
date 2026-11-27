<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\SocialProfiles\Actions;

use AIArmada\Contacting\Data\SocialProfileData;
use AIArmada\Contacting\Enums\SocialPlatform;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class ValidateSocialProfileData
{
    public function execute(SocialProfileData | array $data): SocialProfileData
    {
        if (is_array($data)) {
            $payload = [];
            foreach ($data as $key => $value) {
                $camel = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', (string) $key))));
                $payload[$camel] = $value;
            }

            $validated = Validator::make($payload, self::rules())->validate();

            foreach (['isPrimary', 'isPublic', 'isVerified'] as $key) {
                if (isset($validated[$key])) {
                    $validated[$key] = (bool) $validated[$key];
                }
            }

            if (isset($validated['sortOrder'])) {
                $validated['sortOrder'] = (int) $validated['sortOrder'];
            }

            return SocialProfileData::from($validated);
        }

        Validator::make($data->toArray(), self::rules())->validate();

        return $data;
    }

    /**
     * Rules run against the validated payload, so absent values are
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
