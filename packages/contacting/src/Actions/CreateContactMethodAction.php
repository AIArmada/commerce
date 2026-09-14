<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Actions;

use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Contacting\Models\ContactMethod;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

final class CreateContactMethodAction
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

    public function execute(Model $contactable, ContactMethodData | array $data): ContactMethod
    {
        if (is_array($data)) {
            $data = ContactMethodData::from($data);
        }

        ContactMethodData::validate($data->toArray());

        $countryCode = $data->countryCode instanceof Optional ? null : $data->countryCode;

        $result = $this->normalizer->execute(
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
        $contactMethod->purpose = $data->purpose instanceof Optional ? 'general' : $data->purpose;
        $contactMethod->label = $data->label instanceof Optional ? null : $data->label;
        $contactMethod->value = $data->value;
        $contactMethod->normalized_value = $result['normalized_value'];
        $contactMethod->display_value = ! $data->displayValue instanceof Optional && $data->displayValue !== null
            ? $data->displayValue
            : $result['display_value'];
        $contactMethod->country_code = $countryCode;
        $contactMethod->is_primary = ! $data->isPrimary instanceof Optional && $data->isPrimary;
        $contactMethod->is_public = $data->isPublic;
        $contactMethod->is_verified = ! $data->isVerified instanceof Optional && $data->isVerified;
        $contactMethod->verified_at = $data->verifiedAt instanceof Optional ? null : $data->verifiedAt;
        $contactMethod->valid_from = self::toImmutable($data->validFrom);
        $contactMethod->valid_until = self::toImmutable($data->validUntil);
        $contactMethod->metadata = $data->metadata instanceof Optional ? [] : $data->metadata;

        if ($data->sortOrder !== null) {
            $contactMethod->sort_order = $data->sortOrder;
        }

        DB::transaction(function () use ($contactMethod): void {
            $contactMethod->save();
        });

        return $contactMethod;
    }
}
