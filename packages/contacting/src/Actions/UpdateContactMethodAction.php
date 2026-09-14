<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Actions;

use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Contacting\Models\ContactMethod;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

final class UpdateContactMethodAction
{
    public function __construct(
        private readonly NormalizeContactMethodAction $normalizer,
    ) {}

    private static function toImmutable(DateTimeInterface | string | null $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof CarbonImmutable ? $value : CarbonImmutable::parse($value);
    }

    public function execute(ContactMethod $contactMethod, ContactMethodData | array $data): ContactMethod
    {
        if (is_array($data)) {
            $data = ContactMethodData::from($data);
        }

        ContactMethodData::validate($data->toArray());

        $countryCode = $data->countryCode instanceof Optional
            ? $contactMethod->country_code
            : $data->countryCode;

        $result = $this->normalizer->execute(
            $data->type,
            $data->value,
            $countryCode ?? $contactMethod->country_code ?? config('contacting.defaults.country_code'),
        );

        if ($data->type === 'email' && $result['normalized_value'] === null) {
            throw ValidationException::withMessages([
                'value' => 'The value must be a valid email address for the email type.',
            ]);
        }

        DB::transaction(function () use ($contactMethod, $data, $result, $countryCode): void {
            $contactMethod->type = $data->type;

            if (! $data->purpose instanceof Optional) {
                $contactMethod->purpose = $data->purpose;
            }

            $contactMethod->label = $data->label instanceof Optional ? $contactMethod->label : $data->label;
            $contactMethod->value = $data->value;
            $contactMethod->country_code = $countryCode;
            $contactMethod->normalized_value = $result['normalized_value'];

            if (! $data->displayValue instanceof Optional && $data->displayValue !== null) {
                $contactMethod->display_value = $data->displayValue;
            } elseif ($contactMethod->isDirty(['value', 'country_code']) || $contactMethod->display_value === null) {
                $contactMethod->display_value = $result['display_value'];
            }

            if (! $data->isPrimary instanceof Optional) {
                $contactMethod->is_primary = $data->isPrimary;
            }

            $contactMethod->is_public = $data->isPublic ?? $contactMethod->is_public;

            if (! $data->isVerified instanceof Optional) {
                $contactMethod->is_verified = $data->isVerified;
            }

            if ($data->verifiedAt !== null && ! $data->verifiedAt instanceof Optional) {
                $contactMethod->verified_at = $data->verifiedAt;
            }

            if ($data->validFrom !== null) {
                $contactMethod->valid_from = self::toImmutable($data->validFrom);
            }

            if ($data->validUntil !== null) {
                $contactMethod->valid_until = self::toImmutable($data->validUntil);
            }

            if ($data->sortOrder !== null) {
                $contactMethod->sort_order = $data->sortOrder;
            }

            if (! $data->metadata instanceof Optional) {
                $contactMethod->metadata = $data->metadata;
            }

            $contactMethod->save();
        });

        return $contactMethod->refresh();
    }
}
