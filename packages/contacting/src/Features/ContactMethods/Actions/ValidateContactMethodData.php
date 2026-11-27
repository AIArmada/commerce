<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\ContactMethods\Actions;

use AIArmada\Contacting\Data\ContactMethodData;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class ValidateContactMethodData
{
    public function execute(ContactMethodData | array $data): ContactMethodData
    {
        if (is_array($data)) {
            $payload = [];
            foreach ($data as $key => $value) {
                $camel = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', (string) $key))));
                if ($camel === 'isPrimary' && $value === null) {
                    continue;
                }

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

            return ContactMethodData::from($validated);
        }

        Validator::make($data->toArray(), self::rules())->validate();

        return $data;
    }

    /**
     * Rules run against the validated payload, so absent values are
     * skipped automatically. Email-format enforcement per type lives in the
     * create/update actions, which can see the whole data object.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        $typeValues = self::configValues('contacting.contact_methods.types');
        $purposeValues = self::configValues('contacting.contact_methods.purposes');
        $strictTypes = (bool) config('contacting.features.strict_contact_types', false);

        return [
            'type' => array_values(array_filter([
                'required',
                'string',
                'max:255',
                $strictTypes && $typeValues !== [] ? Rule::in($typeValues) : null,
            ])),
            'purpose' => array_values(array_filter([
                'string',
                'max:255',
                $purposeValues !== [] ? Rule::in($purposeValues) : null,
            ])),
            'label' => ['nullable', 'string', 'max:255'],
            'value' => ['required', 'string'],
            'displayValue' => ['nullable', 'string'],
            'countryCode' => ['nullable', 'string', 'size:2'],
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
}
