<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Support;

use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class TrackedPropertyMutationGuard
{
    public function __construct(private readonly SignalsModelReferenceGuard $referenceGuard) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitize(array $data, string $field = 'tracked_property_id'): array
    {
        $data[$field] = $this->sanitizeTrackedPropertyId($data[$field] ?? null, $field);

        return $data;
    }

    private function sanitizeTrackedPropertyId(mixed $trackedPropertyId, string $field): ?string
    {
        if (! is_string($trackedPropertyId) || $trackedPropertyId === '') {
            return null;
        }

        try {
            $this->referenceGuard->findOrFail(
                TrackedProperty::class,
                $trackedPropertyId,
                includeGlobal: false,
                message: 'Selected tracked property is not accessible.',
            );
        } catch (AuthorizationException | InvalidArgumentException) {
            throw ValidationException::withMessages([
                $field => 'Selected tracked property is not accessible.',
            ]);
        }

        return $trackedPropertyId;
    }
}
