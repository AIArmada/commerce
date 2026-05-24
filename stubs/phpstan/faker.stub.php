<?php

declare(strict_types=1);

namespace Faker;

use DateTimeImmutable;
use DateTimeInterface;

class Generator
{
    public string $uuid;

    public string $slug;

    public string $sentence;

    public function address(): string {}

    public function boolean(int $chanceOfGettingTrue = 50): bool {}

    public function bothify(string $string = '??##'): string {}

    public function city(): string {}

    public function company(): string {}

    public function companyEmail(): string {}

    public function country(): string {}

    public function countryCode(): string {}

    public function dateTimeBetween(DateTimeInterface | string $startDate = '-30 years', DateTimeInterface | string $endDate = 'now', ?string $timezone = null): DateTimeImmutable {}

    public function ean13(): string {}

    public function email(): string {}

    public function firstName(): string {}

    public function imageUrl(int $width = 640, int $height = 480, ?string $category = null, bool $randomize = true, ?string $word = null, bool $gray = false, string $format = 'png'): string {}

    public function ipv4(): string {}

    public function lastName(): string {}

    public function latitude(): float {}

    public function lexify(string $string = '????'): string {}

    public function longitude(): float {}

    public function name(): string {}

    public function numberBetween(int $min = 0, int $max = 2147483647): int {}

    public function numerify(string $string = '###'): string {}

    public function optional(float $weight = 0.5, mixed $default = null): self {}

    public function paragraph(int $nbSentences = 3, bool $variableNbSentences = true): string {}

    /**
     * @return array<int, string>|string
     */
    public function paragraphs(int $nb = 3, bool $asText = false): array | string {}

    public function phoneNumber(): string {}

    public function passthrough(mixed $value): mixed {}

    public function postcode(): string {}

    /**
     * @param  array<int|string, mixed>  $array
     */
    public function randomElement(array $array = []): mixed {}

    public function randomFloat(int $nbMaxDecimals = 0, int | float $min = 0, int | float $max = 2147483647): float {}

    public function randomNumber(?int $nbDigits = null, bool $strict = false): int {}

    public function regexify(string $regex = ''): string {}

    public function safeColorName(): string {}

    public function safeEmail(): string {}

    public function secondaryAddress(): string {}

    public function sentence(int $nbWords = 6, bool $variableNbWords = true): string {}

    public function state(): string {}

    public function stateAbbr(): string {}

    public function streetAddress(): string {}

    public function unique(bool $reset = false, int $maxRetries = 10000): self {}

    public function url(): string {}

    public function userAgent(): string {}

    public function uuid(): string {}

    public function word(): string {}

    /**
     * @return array<int, string>|string
     */
    public function words(int $nb = 3, bool $asText = false): array | string {}
}
