<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Attribution\Support;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateReferenceReader;
use AIArmada\Affiliates\Features\Programs\Api\ProgramReferenceReader;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class TrackingLinkFormReferences
{
    public function __construct(
        private AffiliateReferenceReader $affiliates,
        private ProgramReferenceReader $programs,
    ) {}

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function validate(array $data): array
    {
        $affiliateId = $data['affiliate_id'] ?? null;
        $programId = $data['program_id'] ?? null;

        if (! is_string($affiliateId) || ! Str::isUuid($affiliateId)) {
            throw ValidationException::withMessages(['data.affiliate_id' => 'The selected affiliate is invalid.']);
        }

        if (! $this->affiliates->exists($affiliateId)) {
            $this->reject('affiliate');
        }

        if ($programId === null || $programId === '') {
            $data['program_id'] = null;

            return $data;
        }

        if (! is_string($programId) || ! Str::isUuid($programId)) {
            throw ValidationException::withMessages(['data.program_id' => 'The selected program is invalid.']);
        }

        if (! $this->programs->exists($programId)) {
            $this->reject('program');
        }

        return $data;
    }

    private function reject(string $subject): never
    {
        $message = config('affiliates.owner.enabled', false)
            ? 'The selected ' . $subject . ' is not accessible in the current owner scope.'
            : 'The selected ' . $subject . ' is invalid.';

        throw ValidationException::withMessages(['data.' . $subject . '_id' => $message]);
    }
}
