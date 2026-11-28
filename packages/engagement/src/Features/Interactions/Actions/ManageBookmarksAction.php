<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Actions;

use AIArmada\Engagement\Contracts\Bookmarkable;
use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\EngagementPolicyResolver;
use AIArmada\Engagement\Enums\BookmarkStatus;
use AIArmada\Engagement\Events\BookmarkArchived;
use AIArmada\Engagement\Events\BookmarkCreated;
use AIArmada\Engagement\Events\BookmarkRemoved;
use AIArmada\Engagement\Features\Interactions\Models\Bookmark;
use AIArmada\Engagement\Support\EngagementModelGuard;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;

final readonly class ManageBookmarksAction
{
    public function __construct(
        private EngagementPolicyResolver $policy,
    ) {}

    public function bookmark(CanInteract $actor, Bookmarkable $subject, array $options = []): Bookmark
    {
        $this->assertModels($actor, $subject, Bookmarkable::class);

        $notes = EngagementModelGuard::boundedString($options['notes'] ?? null, 'notes', EngagementModelGuard::TEXT_MAX_LENGTH);
        $source = EngagementModelGuard::boundedString($options['source'] ?? null, 'source');
        $metadata = EngagementModelGuard::optionalArray($options['metadata'] ?? null, 'metadata');

        $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
        $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');

        try {
            return (new Bookmark)->getConnection()->transaction(function () use ($actor, $subject, $notes, $source, $metadata, $actorIdentity, $subjectIdentity): Bookmark {
                $this->authorize(
                    $this->policy->canBookmark($actor, $subject),
                    'Bookmarking this subject is not authorized.',
                );

                $existing = Bookmark::query()->forOwner()
                    ->where('bookmarker_type', $actorIdentity['type'])
                    ->where('bookmarker_id', $actorIdentity['id'])
                    ->where('bookmarkable_type', $subjectIdentity['type'])
                    ->where('bookmarkable_id', $subjectIdentity['id'])
                    ->lockForUpdate()
                    ->first();

                if ($existing && $existing->status === BookmarkStatus::Active) {
                    return $existing;
                }

                if ($existing) {
                    $existing->update([
                        'status' => 'active',
                        'removed_at' => null,
                        'archived_at' => null,
                        'bookmarked_at' => CarbonImmutable::now(),
                    ]);
                    event(new BookmarkCreated(
                        $existing->id,
                        $existing->bookmarker_type,
                        $existing->bookmarker_id,
                        $existing->bookmarkable_type,
                        $existing->bookmarkable_id,
                        $existing->owner_type,
                        $existing->owner_id,
                    ));

                    return $existing;
                }

                $bookmark = Bookmark::query()->create([
                    'bookmarker_type' => $actorIdentity['type'],
                    'bookmarker_id' => $actorIdentity['id'],
                    'bookmarkable_type' => $subjectIdentity['type'],
                    'bookmarkable_id' => $subjectIdentity['id'],
                    'status' => 'active',
                    'notes' => $notes,
                    'bookmarked_at' => CarbonImmutable::now(),
                    'source' => $source,
                    'metadata' => $metadata,
                ]);

                event(new BookmarkCreated(
                    $bookmark->id,
                    $bookmark->bookmarker_type,
                    $bookmark->bookmarker_id,
                    $bookmark->bookmarkable_type,
                    $bookmark->bookmarkable_id,
                    $bookmark->owner_type,
                    $bookmark->owner_id,
                ));

                return $bookmark;
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $existing = Bookmark::query()->forOwner()
                ->where('bookmarker_type', $actorIdentity['type'])
                ->where('bookmarker_id', $actorIdentity['id'])
                ->where('bookmarkable_type', $subjectIdentity['type'])
                ->where('bookmarkable_id', $subjectIdentity['id'])
                ->first();

            if ($existing instanceof Bookmark && $existing->status === BookmarkStatus::Active) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function removeBookmark(CanInteract $actor, Bookmarkable $subject, array $options = []): void
    {
        $this->assertModels($actor, $subject, Bookmarkable::class);

        (new Bookmark)->getConnection()->transaction(function () use ($actor, $subject): void {
            $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
            $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');
            $bookmark = Bookmark::query()->forOwner()
                ->where('bookmarker_type', $actorIdentity['type'])
                ->where('bookmarker_id', $actorIdentity['id'])
                ->where('bookmarkable_type', $subjectIdentity['type'])
                ->where('bookmarkable_id', $subjectIdentity['id'])
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($bookmark) {
                $bookmark->update(['status' => 'removed', 'removed_at' => CarbonImmutable::now()]);
                event(new BookmarkRemoved(
                    $bookmark->id,
                    $bookmark->bookmarker_type,
                    $bookmark->bookmarker_id,
                    $bookmark->bookmarkable_type,
                    $bookmark->bookmarkable_id,
                    $bookmark->owner_type,
                    $bookmark->owner_id,
                ));
            }
        });
    }

    public function archiveBookmark(CanInteract $actor, Bookmarkable $subject, array $options = []): void
    {
        $this->assertModels($actor, $subject, Bookmarkable::class);

        (new Bookmark)->getConnection()->transaction(function () use ($actor, $subject): void {
            $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
            $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');
            $bookmark = Bookmark::query()->forOwner()
                ->where('bookmarker_type', $actorIdentity['type'])
                ->where('bookmarker_id', $actorIdentity['id'])
                ->where('bookmarkable_type', $subjectIdentity['type'])
                ->where('bookmarkable_id', $subjectIdentity['id'])
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($bookmark) {
                $bookmark->update(['status' => 'archived', 'archived_at' => CarbonImmutable::now()]);
                event(new BookmarkArchived(
                    $bookmark->id,
                    $bookmark->bookmarker_type,
                    $bookmark->bookmarker_id,
                    $bookmark->bookmarkable_type,
                    $bookmark->bookmarkable_id,
                    $bookmark->owner_type,
                    $bookmark->owner_id,
                ));
            }
        });
    }

    private function assertModels(CanInteract $actor, object $subject, string $subjectContract): void
    {
        EngagementModelGuard::assertContract($actor, CanInteract::class, 'actor');
        EngagementModelGuard::assertContract($subject, $subjectContract, 'subject');
        EngagementModelGuard::assertWritableIdentity($actor, 'actor');
        EngagementModelGuard::assertWritableIdentity($subject, 'subject');
    }

    private function authorize(bool $allowed, string $message): void
    {
        if (! $allowed) {
            throw new AuthorizationException($message);
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }
}
