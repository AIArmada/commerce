<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Events\Contracts\EventOrderItemFulfillmentResolver;
use AIArmada\Events\Models\EventRegistrationItem;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

final class DefaultEventOrderItemFulfillmentResolver implements EventOrderItemFulfillmentResolver
{
    public function resolve(EventRegistrationItem $registrationItem): mixed
    {
        $registration = $registrationItem->registration;

        if ($registration === null) {
            return null;
        }

        $participants = $registration->participants()
            ->with('contactMethods')
            ->get();

        if ($participants->isEmpty()) {
            return null;
        }

        return [
            'event_id' => $registration->event_id,
            'event_occurrence_id' => $registration->event_occurrence_id,
            'ticket_type_id' => $registrationItem->event_ticket_type_id,
            'participants' => $participants->map(function (mixed $participant): array {
                return array_filter([
                    'name' => $participant->name,
                    'email' => $this->resolveEmail($participant),
                    'phone' => $this->resolvePhone($participant),
                    'is_primary' => $participant->is_primary,
                ], static fn (mixed $value): bool => $value !== null);
            })->values()->toArray(),
        ];
    }

    private function resolveEmail(mixed $participant): ?string
    {
        $email = $this->resolveAttribute($participant, 'email');

        if ($email !== null) {
            return $email;
        }

        $contact = $this->resolveContactMethod($participant, 'email');

        if ($contact === null) {
            return null;
        }

        return $this->resolveContactValue($contact);
    }

    private function resolvePhone(mixed $participant): ?string
    {
        $phone = $this->resolveAttribute($participant, 'phone');

        if ($phone !== null) {
            return $phone;
        }

        $contact = $this->resolveContactMethod($participant, 'phone');

        if ($contact === null) {
            return null;
        }

        return $this->resolveContactValue($contact);
    }

    private function resolveAttribute(mixed $model, string $key): ?string
    {
        if ($model === null || ! method_exists($model, 'getAttribute')) {
            return null;
        }

        $value = $model->getAttribute($key);

        return is_string($value) && mb_trim($value) !== '' ? mb_trim($value) : null;
    }

    private function resolveContactMethod(mixed $participant, string $type): ?ContactMethod
    {
        if (! $participant instanceof Model || ! method_exists($participant, 'contactMethods')) {
            return null;
        }

        $contact = null;

        if (method_exists($participant, 'relationLoaded') && $participant->relationLoaded('contactMethods')) {
            /** @var EloquentCollection<int, ContactMethod> $contactMethods */
            $contactMethods = $participant->getRelation('contactMethods');

            $contact = $contactMethods
                ->filter(static fn (ContactMethod $contact): bool => $contact->type === $type)
                ->sort(function (ContactMethod $left, ContactMethod $right): int {
                    return ($right->is_primary <=> $left->is_primary)
                        ?: ($left->sort_order <=> $right->sort_order);
                })
                ->first();
        } else {
            /** @var MorphMany $contactMethods */
            $contactMethods = $participant->contactMethods();

            $contact = $contactMethods
                ->where('type', $type)
                ->orderByDesc('is_primary')
                ->orderBy('sort_order')
                ->first();
        }

        return $contact instanceof ContactMethod ? $contact : null;
    }

    private function resolveContactValue(ContactMethod $contact): ?string
    {
        $value = $contact->getAttribute('normalized_value')
            ?? $contact->getAttribute('value');

        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }
}
