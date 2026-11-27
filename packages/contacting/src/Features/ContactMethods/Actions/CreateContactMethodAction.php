<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\ContactMethods\Actions;

use AIArmada\Contacting\Contracts\ContactMethodNormalizer;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Contacting\Data\MissingValue;
use AIArmada\Contacting\Features\ContactMethods\Models\ContactMethod;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CreateContactMethodAction
{
    public function __construct(
        private readonly ContactMethodNormalizer $normalizer,
    ) {}

    private static function toImmutable(DateTimeInterface | string | null $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof CarbonImmutable ? $value : CarbonImmutable::parse($value);
    }

    public function execute(Model $contactable, ContactMethodData | array $data): ContactMethod
    {
        $data = app(ValidateContactMethodData::class)->execute($data);

        $countryCode = $data->countryCode instanceof MissingValue ? null : $data->countryCode;

        $result = $this->normalizer->normalize(
            $data->type,
            $data->value,
            $countryCode ?? config('contacting.defaults.country_code'),
        );

        if ($data->type === 'email' && $result['normalized_value'] === null) {
            throw ValidationException::withMessages([
                'value' => 'The value must be a valid email address for the email type.',
            ]);
        }

        $contactMethod = new ContactMethod;
        $contactMethod->contactable()->associate($contactable);
        $contactMethod->type = $data->type;
        $contactMethod->purpose = $data->purpose instanceof MissingValue ? 'general' : $data->purpose;
        $contactMethod->label = $data->label instanceof MissingValue ? null : $data->label;
        $contactMethod->value = $data->value;
        $contactMethod->normalized_value = $result['normalized_value'];
        $contactMethod->display_value = ! $data->displayValue instanceof MissingValue && $data->displayValue !== null
            ? $data->displayValue
            : $result['display_value'];
        $contactMethod->country_code = $countryCode;
        $contactMethod->is_primary = ! $data->isPrimary instanceof MissingValue && $data->isPrimary;
        $contactMethod->is_public = $data->isPublic;
        $contactMethod->is_verified = ! $data->isVerified instanceof MissingValue && $data->isVerified;
        $contactMethod->verified_at = $data->verifiedAt instanceof MissingValue ? null : $data->verifiedAt;
        $contactMethod->valid_from = self::toImmutable($data->validFrom);
        $contactMethod->valid_until = self::toImmutable($data->validUntil);
        $contactMethod->metadata = $data->metadata instanceof MissingValue ? [] : $data->metadata;

        if ($data->sortOrder !== null) {
            $contactMethod->sort_order = $data->sortOrder;
        }

        $contactMethod->getConnection()->transaction(function () use ($contactMethod): void {
            if (! app(SynchronizePrimaryContactMethodAction::class)->execute($contactMethod)) {
                throw new RuntimeException('The contact write was cancelled.');
            }
        });

        return $contactMethod;
    }
}
