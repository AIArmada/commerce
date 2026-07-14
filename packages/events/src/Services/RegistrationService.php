<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Events\EventRegistrationApproved;
use AIArmada\Events\Events\EventRegistrationCancelled;
use AIArmada\Events\Events\EventRegistrationCompleted;
use AIArmada\Events\Events\EventRegistrationCreated;
use AIArmada\Events\Events\EventRegistrationRefunded;
use AIArmada\Events\Events\EventRegistrationRejected;
use AIArmada\Events\Events\EventRegistrationWaitlisted;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventRegistrationParticipant;
use AIArmada\Events\States\RegistrationStatus\Cancelled;
use AIArmada\Events\States\RegistrationStatus\Completed;
use AIArmada\Events\States\RegistrationStatus\Confirmed;
use AIArmada\Events\States\RegistrationStatus\Refunded;
use AIArmada\Events\States\RegistrationStatus\Rejected;
use AIArmada\Events\States\RegistrationStatus\Waitlisted;
use AIArmada\Events\Support\EventWriteGuard;
use AIArmada\Events\Support\ModelResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class RegistrationService implements RegistrationServiceInterface
{
    public function register(array $data): EventRegistration
    {
        EventWriteGuard::findOrFail($data['event_id']);

        $registration = DB::transaction(function () use ($data): EventRegistration {
            $registrationClass = ModelResolver::registrationClass();
            $registration = $registrationClass::create(Arr::except($data, ['items', 'participants', 'answers']));

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

            return $registration;
        });

        event(new EventRegistrationCreated($registration));

        return $registration;
    }

    public function approve(EventRegistration $registration, mixed $actor = null): void
    {
        EventWriteGuard::findOrFail($registration->event_id);

        if ($registration->status instanceof Confirmed) {
            if ($registration->approved_at === null) {
                $registration->update(['approved_at' => CarbonImmutable::now()]);
            }

            return;
        }

        $registration->approved_at = CarbonImmutable::now();
        $registration->status->transitionTo(Confirmed::class);

        event(new EventRegistrationApproved($registration));
    }

    public function cancel(EventRegistration $registration, ?string $reason = null, mixed $actor = null): void
    {
        EventWriteGuard::findOrFail($registration->event_id);

        if ($registration->status instanceof Cancelled) {
            if ($registration->cancelled_at === null) {
                $registration->update([
                    'cancelled_at' => CarbonImmutable::now(),
                    'status_reason' => $reason,
                ]);
            }

            return;
        }

        $registration->cancelled_at = CarbonImmutable::now();
        $registration->status_reason = $reason;
        $registration->status->transitionTo(Cancelled::class);

        event(new EventRegistrationCancelled($registration, $reason));
    }

    public function reject(EventRegistration $registration, string $reason, mixed $actor = null): void
    {
        EventWriteGuard::findOrFail($registration->event_id);

        if ($registration->status instanceof Rejected) {
            if ($registration->rejected_at === null) {
                $registration->update([
                    'rejected_at' => CarbonImmutable::now(),
                    'status_reason' => $reason,
                ]);
            }

            return;
        }

        $registration->rejected_at = CarbonImmutable::now();
        $registration->status_reason = $reason;
        $registration->status->transitionTo(Rejected::class);

        event(new EventRegistrationRejected($registration, $reason));
    }

    public function waitlist(EventRegistration $registration): void
    {
        EventWriteGuard::findOrFail($registration->event_id);

        if ($registration->status instanceof Waitlisted) {
            if ($registration->waitlisted_at === null) {
                $registration->update(['waitlisted_at' => CarbonImmutable::now()]);
            }

            return;
        }

        $registration->waitlisted_at = CarbonImmutable::now();
        $registration->status->transitionTo(Waitlisted::class);

        event(new EventRegistrationWaitlisted($registration));
    }

    public function complete(EventRegistration $registration): void
    {
        EventWriteGuard::findOrFail($registration->event_id);

        if ($registration->status instanceof Completed) {
            if ($registration->completed_at === null) {
                $registration->update(['completed_at' => CarbonImmutable::now()]);
            }

            return;
        }

        $registration->completed_at = CarbonImmutable::now();
        $registration->status->transitionTo(Completed::class);

        event(new EventRegistrationCompleted($registration));
    }

    public function refund(EventRegistration $registration, ?string $reason = null): void
    {
        EventWriteGuard::findOrFail($registration->event_id);

        if ($registration->status instanceof Refunded) {
            if ($registration->refunded_at === null) {
                $registration->update([
                    'refunded_at' => CarbonImmutable::now(),
                    'status_reason' => $reason,
                ]);
            }

            return;
        }

        $registration->refunded_at = CarbonImmutable::now();
        $registration->status_reason = $reason;
        $registration->status->transitionTo(Refunded::class);

        event(new EventRegistrationRefunded($registration, $reason));
    }

    public function createFromOrderItem(array $orderItemData): void
    {
        EventWriteGuard::findOrFail($orderItemData['event_id']);

        $registrationClass = ModelResolver::registrationClass();
        $registration = $registrationClass::create([
            'event_id' => $orderItemData['event_id'],
            'event_occurrence_id' => $orderItemData['event_occurrence_id'] ?? null,
            'event_session_id' => $orderItemData['event_session_id'] ?? null,
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
