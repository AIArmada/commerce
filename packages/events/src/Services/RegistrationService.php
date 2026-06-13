<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Events\EventRegistrationApproved;
use AIArmada\Events\Events\EventRegistrationCancelled;
use AIArmada\Events\Events\EventRegistrationConfirmed;
use AIArmada\Events\Events\EventRegistrationCreated;
use AIArmada\Events\Events\EventRegistrationRejected;
use AIArmada\Events\Events\EventRegistrationWaitlisted;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventRegistrationParticipant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

final class RegistrationService implements RegistrationServiceInterface
{
    public function register(array $data): EventRegistration
    {
        $registration = EventRegistration::create(Arr::except($data, ['items', 'participants', 'answers']));

        $scopeFields = [
            'event_id' => $registration->event_id,
            'event_occurrence_id' => $registration->event_occurrence_id,
            'event_session_id' => $registration->event_session_id,
        ];

        if (isset($data['participants'])) {
            foreach ($data['participants'] as $participantData) {
                $extraFields = Arr::only($participantData, ['email', 'phone', 'company', 'is_purchaser']);
                $participantFields = array_merge(
                    Arr::except($participantData, ['email', 'phone', 'company', 'is_purchaser']),
                    $scopeFields,
                    ['metadata' => array_filter(['contact' => $extraFields])],
                );
                $participant = $registration->participants()->create($participantFields);

                if ($participant instanceof EventRegistrationParticipant) {
                    $this->syncParticipantContactMethods($participant, $participantData);
                }
            }
        }

        if (isset($data['items'])) {
            foreach ($data['items'] as $itemData) {
                $registration->items()->create(array_merge($itemData, $scopeFields));
            }
        }

        if (isset($data['answers'])) {
            foreach ($data['answers'] as $answerData) {
                $registration->answers()->create(array_merge($answerData, $scopeFields));
            }
        }

        event(new EventRegistrationCreated($registration));

        return $registration;
    }

    public function approve(EventRegistration $registration, mixed $actor = null): void
    {
        $registration->update([
            'status' => 'confirmed',
            'approved_at' => CarbonImmutable::now(),
        ]);

        event(new EventRegistrationApproved($registration));
    }

    public function cancel(EventRegistration $registration, ?string $reason = null, mixed $actor = null): void
    {
        $registration->update([
            'status' => 'cancelled',
            'cancelled_at' => CarbonImmutable::now(),
            'status_reason' => $reason,
        ]);

        event(new EventRegistrationCancelled($registration, $reason));
    }

    public function reject(EventRegistration $registration, string $reason, mixed $actor = null): void
    {
        $registration->update([
            'status' => 'rejected',
            'rejected_at' => CarbonImmutable::now(),
            'status_reason' => $reason,
        ]);

        event(new EventRegistrationRejected($registration, $reason));
    }

    public function waitlist(EventRegistration $registration): void
    {
        $registration->update([
            'status' => 'waitlisted',
            'waitlisted_at' => CarbonImmutable::now(),
        ]);

        event(new EventRegistrationWaitlisted($registration));
    }

    public function complete(EventRegistration $registration): void
    {
        $registration->update([
            'status' => 'completed',
            'approved_at' => CarbonImmutable::now(),
        ]);

        event(new EventRegistrationConfirmed($registration));
    }

    public function createFromOrderItem(array $orderItemData, ?string $orderItemId = null, ?string $orderItemType = null): void
    {
        $registration = EventRegistration::create([
            'event_id' => $orderItemData['event_id'],
            'event_occurrence_id' => $orderItemData['event_occurrence_id'] ?? null,
            'registrant_type' => $orderItemData['registrant_type'] ?? null,
            'registrant_id' => $orderItemData['registrant_id'] ?? null,
            'registration_type' => $orderItemData['registration_type'] ?? 'standard',
            'status' => 'pending',
            'source' => 'order',
            'total_participants' => $orderItemData['quantity'] ?? 1,
            'total_amount' => $orderItemData['total_price'] ?? 0,
            'currency' => $orderItemData['currency'] ?? 'USD',
            'external_order_id' => $orderItemData['order_id'] ?? null,
            'external_order_type' => $orderItemData['order_type'] ?? null,
        ]);

        event(new EventRegistrationCreated($registration));
    }

    public function syncByOrder(string $orderId, string $orderType, string $eventType): void {}

    /**
     * @param  array<string, mixed>  $participantData
     */
    private function syncParticipantContactMethods(EventRegistrationParticipant $participant, array $participantData): void
    {
        $email = $this->cleanString($participantData['email'] ?? null);

        if ($email !== null) {
            $participant->addContactMethod(new ContactMethodData(
                type: 'email',
                purpose: 'general',
                value: $email,
                isPrimary: true,
            ));
        }

        $phone = $this->cleanString($participantData['phone'] ?? null);

        if ($phone !== null) {
            $participant->addContactMethod(new ContactMethodData(
                type: 'phone',
                purpose: 'general',
                value: $phone,
                countryCode: config('contacting.defaults.country_code', 'MY'),
                isPrimary: true,
            ));
        }
    }

    private function cleanString(mixed $value): ?string
    {
        if ($value === null || ! is_scalar($value)) {
            return null;
        }

        $cleaned = mb_trim((string) $value);

        return $cleaned === '' ? null : $cleaned;
    }
}
