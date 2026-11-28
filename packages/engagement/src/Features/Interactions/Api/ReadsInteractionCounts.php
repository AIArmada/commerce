<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Api;

interface ReadsInteractionCounts
{
    public function countBookmarksByIdentity(string $subjectType, string $subjectId): int;

    public function countFollowersByIdentity(string $subjectType, string $subjectId): int;

    public function countResponsesByIdentity(string $subjectType, string $subjectId, ?string $responseType = null): int;

    public function countReactionsByIdentity(string $subjectType, string $subjectId, ?string $reactionType = null): int;

    /** @return array<string, int> */
    public function groupedResponseCounts(string $subjectType, string $subjectId): array;

    /** @return array<string, int> */
    public function groupedReactionCounts(string $subjectType, string $subjectId): array;

    /** @return array<string, int> */
    public function aggregateCounters(string $subjectType, string $subjectId): array;
}
