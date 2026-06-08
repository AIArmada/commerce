<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventCheckoutIntentResolver;
use AIArmada\Events\Data\EventCheckoutIntentData;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Products\Models\Variant;
use Illuminate\Database\Eloquent\Model;

final class DefaultEventCheckoutIntentResolver implements EventCheckoutIntentResolver
{
    public function resolve(Occurrence $occurrence, int $quantity = 1, array $metadata = []): ?EventCheckoutIntentData
    {
        if (! $occurrence->isPaidRegistration()) {
            return null;
        }

        $occurrence->loadMissing(['variant', 'product']);

        $buyable = $occurrence->variant ?? $occurrence->product;

        if (! $buyable instanceof Model) {
            return null;
        }

        return new EventCheckoutIntentData(
            buyable: $buyable,
            cartItemId: sprintf('occurrence-%s', $occurrence->getKey()),
            quantity: max(1, $quantity),
            attributes: [
                'event_id' => $occurrence->event_id,
                'occurrence_id' => $occurrence->id,
                'product_id' => $occurrence->product_id
                    ?? ($occurrence->variant instanceof Variant ? $occurrence->variant->product_id : null),
                'variant_id' => $occurrence->variant_id,
                'registration_mode' => $occurrence->registration_mode,
                'schedule_mode' => $occurrence->schedule_mode,
                'schedule_reference_key' => $occurrence->schedule_reference_key,
                'schedule_reference_payload' => $occurrence->schedule_reference_payload,
                'schedule_label' => $occurrence->schedule_label,
                'starts_at' => $occurrence->starts_at->toISOString(),
                'ends_at' => $occurrence->ends_at?->toISOString(),
                'timezone' => $occurrence->timezone,
            ],
            metadata: array_merge([
                'event_id' => $occurrence->event_id,
                'occurrence_id' => $occurrence->id,
            ], $metadata),
        );
    }
}
