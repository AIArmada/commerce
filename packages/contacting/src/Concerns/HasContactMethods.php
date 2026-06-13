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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasContactMethods
{
    protected static function bootHasContactMethods(): void
    {
        static::deleting(function (Model $model): void {
            /** @phpstan-ignore-next-line */
            $model->contactMethods()->delete();
        });
    }

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

    public function resolveEmail(): ?string
    {
        return $this->resolveContact('email');
    }

    /**
     * @return array<int, string>
     */
    public function resolveEmails(): array
    {
        return $this->resolveContacts('email');
    }

    public function resolvePhone(): ?string
    {
        return $this->resolveContact('phone');
    }

    /**
     * @return array<int, string>
     */
    public function resolvePhones(): array
    {
        return $this->resolveContacts('phone');
    }

    private function resolveContact(string $type): ?string
    {
        $contact = $this->contactMethods()
            ->where('type', $type)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->first();

        return $contact !== null ? $this->normalizeContactValue($contact) : null;
    }

    /**
     * @return array<int, string>
     */
    private function resolveContacts(string $type): array
    {
        return $this->contactMethods()
            ->where('type', $type)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ContactMethod $contact): ?string => $this->normalizeContactValue($contact))
            ->filter(static fn (?string $value): bool => $value !== null)
            ->values()
            ->toArray();
    }

    private function normalizeContactValue(ContactMethod $contact): ?string
    {
        $value = $contact->getAttribute('normalized_value')
            ?? $contact->getAttribute('value');

        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }

    public function addContactMethod(ContactMethodData | array $data): ContactMethod
    {
        if (is_array($data)) {
            $data = ContactMethodData::from($data);
        }

        if (function_exists('app')) {
            return app(CreateContactMethodAction::class)->execute($this, $data);
        }

        return (new CreateContactMethodAction(
            new NormalizeContactMethodAction(
                new NormalizesEmailAddress,
                new NormalizesPhoneNumber,
                new NormalizesUrl,
            ),
        ))->execute($this, $data);
    }
}
