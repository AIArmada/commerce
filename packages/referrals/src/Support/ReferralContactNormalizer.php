<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Support;

use InvalidArgumentException;

final class ReferralContactNormalizer
{
    public function normalize(string $contact): string
    {
        $contact = mb_trim($contact);

        if ($contact === '') {
            throw new InvalidArgumentException('A referral contact is required.');
        }

        if (str_contains($contact, '@')) {
            return mb_strtolower($contact);
        }

        $hasInternationalPrefix = str_starts_with($contact, '+');
        $digits = preg_replace('/\D+/', '', $contact);

        if (! is_string($digits) || $digits === '') {
            throw new InvalidArgumentException('A referral phone contact must contain digits.');
        }

        return ($hasInternationalPrefix ? '+' : '') . $digits;
    }

    public function hash(string $contact): string
    {
        return hash('sha256', $this->normalize($contact));
    }
}
