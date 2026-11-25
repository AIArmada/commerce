<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Actions;

use AIArmada\Events\Features\Registrations\Models\EventRegistration;

final class RecordRegistrationPaymentStatusAction
{
    public function handle(string $registrationId, string $paymentStatus): void
    {
        /** @var EventRegistration|null $registration */
        $registration = EventRegistration::query()->whereKey($registrationId)->first();

        if (! $registration instanceof EventRegistration) {
            return;
        }

        if ($registration->payment_status === $paymentStatus) {
            return;
        }

        $registration->forceFill(['payment_status' => $paymentStatus])->save();
    }
}
