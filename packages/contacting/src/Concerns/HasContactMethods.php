<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Concerns;

use AIArmada\Contacting\Actions\CreateContactMethodAction;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Contacting\Models\ContactMethod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasContactMethods
{
    protected static function bootHasContactMethods(): void
    {
        static::deleting(function (Model $model): void {
            /** @phpstan-ignore-next-line dynamic relationship from trait */
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

    public function primaryContactMethod(?string $type = null, ?string $purpose = null, bool $publicOnly = false): ?ContactMethod
    {
        $now = CarbonImmutable::now();
        $query = $this->contactMethods()
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->when($purpose !== null, fn ($query) => $query->where('purpose', $purpose))
            ->where('is_primary', true)
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
            });

        if ($publicOnly) {
            $query->where('is_public', true);
        }

        return $query->orderBy('sort_order')->first();
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

    public function resolveEmail(bool $publicOnly = false): ?string
    {
        return $this->resolveContact('email', $publicOnly);
    }

    /**
     * @return array<int, string>
     */
    public function resolveEmails(bool $publicOnly = false): array
    {
        return $this->resolveContacts('email', $publicOnly);
    }

    public function resolvePhone(bool $publicOnly = false): ?string
    {
        return $this->resolveContact('phone', $publicOnly);
    }

    /**
     * @return array<int, string>
     */
    public function resolvePhones(bool $publicOnly = false): array
    {
        return $this->resolveContacts('phone', $publicOnly);
    }

    private function resolveContact(string $type, bool $publicOnly = false): ?string
    {
        $now = CarbonImmutable::now();
        $query = $this->contactMethods()
            ->where('type', $type)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
            });

        if ($publicOnly) {
            $query->where('is_public', true);
        }

        $contact = $query->first();

        return $contact !== null ? $this->normalizeContactValue($contact) : null;
    }

    /**
     * @return array<int, string>
     */
    private function resolveContacts(string $type, bool $publicOnly = false): array
    {
        $now = CarbonImmutable::now();
        $query = $this->contactMethods()
            ->where('type', $type)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
            });

        if ($publicOnly) {
            $query->where('is_public', true);
        }

        return $query->get()
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

        return app(CreateContactMethodAction::class)->execute($this, $data);
    }
}
