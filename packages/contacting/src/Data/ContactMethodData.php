<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Data;

use DateTimeInterface;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapInputName(SnakeCaseMapper::class)]
final class ContactMethodData extends Data
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly string $type = '',
        public readonly string | Optional $purpose = new Optional,
        public readonly string | null | Optional $label = new Optional,
        public readonly string $value = '',
        public readonly string | null | Optional $displayValue = new Optional,
        public readonly string | null | Optional $countryCode = new Optional,
        public readonly bool | Optional $isPrimary = new Optional,
        public readonly ?bool $isPublic = null,
        public readonly bool | Optional $isVerified = new Optional,
        public readonly mixed $verifiedAt = null,
        public readonly array | Optional $metadata = new Optional,
        public readonly DateTimeInterface | string | null $validFrom = null,
        public readonly DateTimeInterface | string | null $validUntil = null,
        public readonly ?int $sortOrder = null,
    ) {}

    public static function email(string $email, ?string $purpose = 'general', ?string $label = null): self
    {
        return new self(
            type: 'email',
            purpose: $purpose ?? 'general',
            label: $label,
            value: $email,
        );
    }

    public static function phone(string $phone, ?string $countryCode = null, ?string $purpose = 'general', ?string $label = null): self
    {
        return new self(
            type: 'phone',
            purpose: $purpose ?? 'general',
            label: $label,
            value: $phone,
            countryCode: $countryCode,
        );
    }

    public static function whatsapp(string $phone, ?string $countryCode = null, ?string $purpose = 'general', ?string $label = null): self
    {
        return new self(
            type: 'whatsapp',
            purpose: $purpose ?? 'general',
            label: $label,
            value: $phone,
            countryCode: $countryCode,
        );
    }

    public static function website(string $url, ?string $purpose = 'general', ?string $label = null): self
    {
        return new self(
            type: 'website',
            purpose: $purpose ?? 'general',
            label: $label,
            value: $url,
        );
    }

    /**
     * Rules run against the validated payload, so absent (Optional) values are
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
