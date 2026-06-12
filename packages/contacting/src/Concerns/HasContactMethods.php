<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Concerns;

use AIArmada\Contacting\Actions\CreateContactMethodAction;
use AIArmada\Contacting\Actions\NormalizeContactMethodAction;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Contacting\Support\NormalizesEmailAddress;
use AIArmada\Contacting\Support\NormalizesPhoneNumber;
use AIArmada\Contacting\Support\NormalizesUrl;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasContactMethods
{
    /**
     * @return MorphMany<ContactMethod, $this>
     */
    public function contactMethods(): MorphMany
    {
        return $this->morphMany(ContactMethod::class, 'contactable');
    }

    /**
     * @return MorphMany<ContactMethod, $this>
     */
    public function publicContactMethods(): MorphMany
    {
        return $this->contactMethods()->where('is_public', true);
    }

    public function primaryContactMethod(?string $type = null, ?string $purpose = null): ?ContactMethod
    {
        return $this->contactMethods()
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->when($purpose !== null, fn ($query) => $query->where('purpose', $purpose))
            ->where('is_primary', true)
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * @return MorphMany<ContactMethod, $this>
     */
    public function contactMethodsOfType(string $type): MorphMany
    {
        return $this->contactMethods()->where('type', $type);
    }

    /**
     * @return MorphMany<ContactMethod, $this>
     */
    public function contactMethodsForPurpose(string $purpose): MorphMany
    {
        return $this->contactMethods()->where('purpose', $purpose);
    }

    public function addContactMethod(ContactMethodData | array $data): ContactMethod
    {
        if (is_array($data)) {
            $data = ContactMethodData::from($data);
        }

        if (function_exists('app')) {
            return app(CreateContactMethodAction::class)->execute($this, $data);
        }

        $normalizer = new NormalizesEmailAddress;

        return (new CreateContactMethodAction(
            new NormalizeContactMethodAction(
                new NormalizesEmailAddress,
                new NormalizesPhoneNumber,
                new NormalizesUrl,
            ),
        ))->execute($this, $data);
    }
}
