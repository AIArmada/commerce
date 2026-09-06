<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

/**
 * Contributes generic promotables for the program catalog.
 *
 * Each item must be addressable as subject_type/subject_key so the
 * affiliate-network can mirror it as an offer and reconcile conversions.
 *
 * @return iterable<int, array{subject_key: string, title: string, url: string, amount_minor?: int|null, currency?: string|null, context?: array<string, mixed>}>
 */
interface PromotableProviderInterface
{
    public function type(): string;

    public function list(?string $programId = null): iterable;
}
