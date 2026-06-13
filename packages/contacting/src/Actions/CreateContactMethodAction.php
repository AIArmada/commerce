<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Actions;

use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Contacting\Models\ContactMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Optional;

final class CreateContactMethodAction
{
    public function __construct(
        private readonly NormalizeContactMethodAction $normalizer,
    ) {}

    public function execute(Model $contactable, ContactMethodData | array $data): ContactMethod
    {
        if (is_array($data)) {
            $data = ContactMethodData::from($data);
        }

        $result = $this->normalizer->execute(
            $data->type,
            $data->value,
            $data->countryCode ?? config('contacting.defaults.country_code'),
        );

        $contactMethod = new ContactMethod;
        $contactMethod->contactable()->associate($contactable);
        $contactMethod->type = $data->type;
        $contactMethod->purpose = $data->purpose;
        $contactMethod->label = $data->label instanceof Optional ? null : $data->label;
        $contactMethod->value = $data->value;
        $contactMethod->normalized_value = $result['normalized_value'];
        $contactMethod->display_value = $result['display_value'];
        $contactMethod->country_code = $data->countryCode;
        $contactMethod->is_primary = $data->isPrimary;
        $contactMethod->is_public = $data->isPublic;
        $contactMethod->is_verified = $data->isVerified;
        $contactMethod->metadata = $data->metadata;

        DB::transaction(function () use ($contactMethod): void {
            $contactMethod->save();
        });

        return $contactMethod;
    }
}
