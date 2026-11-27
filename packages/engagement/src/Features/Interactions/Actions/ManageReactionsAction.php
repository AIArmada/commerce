<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Actions;

use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\EngagementPolicyResolver;
use AIArmada\Engagement\Contracts\Reactable;
use AIArmada\Engagement\Enums\ReactionStatus;
use AIArmada\Engagement\Events\ReactionCreated;
use AIArmada\Engagement\Events\ReactionRemoved;
use AIArmada\Engagement\Features\Interactions\Models\Reaction;
use AIArmada\Engagement\Support\EngagementModelGuard;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;

final readonly class ManageReactionsAction
{
    public function __construct(
        private EngagementPolicyResolver $policy,
    ) {}

    public function react(CanInteract $actor, Reactable $subject, string $reactionType, array $options = []): Reaction
    {
        $this->assertModels($actor, $subject, Reactable::class);

        $reactionType = EngagementModelGuard::requiredString($reactionType, 'reaction_type');
        $source = EngagementModelGuard::boundedString($options['source'] ?? null, 'source');
        $metadata = EngagementModelGuard::optionalArray($options['metadata'] ?? null, 'metadata');

        $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
        $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');

        try {
            return (new Reaction)->getConnection()->transaction(function () use ($actor, $subject, $reactionType, $source, $metadata, $actorIdentity, $subjectIdentity): Reaction {
                $this->authorize(
                    $this->policy->canReact($actor, $subject, $reactionType),
                    'Reacting to this subject is not authorized.',
                );

                $existing = Reaction::query()->forOwner()
                    ->where('reactor_type', $actorIdentity['type'])
                    ->where('reactor_id', $actorIdentity['id'])
                    ->where('reactable_type', $subjectIdentity['type'])
                    ->where('reactable_id', $subjectIdentity['id'])
                    ->where('reaction_type', $reactionType)
                    ->lockForUpdate()
                    ->first();

                if ($existing && $existing->status === ReactionStatus::Active) {
                    return $existing;
                }

                if ($existing) {
                    $existing->update([
                        'status' => 'active',
                        'removed_at' => null,
                        'reacted_at' => CarbonImmutable::now(),
                    ]);
                    event(new ReactionCreated(
                        $existing->id,
                        $existing->reactor_type,
                        $existing->reactor_id,
                        $existing->reactable_type,
                        $existing->reactable_id,
                        $existing->owner_type,
                        $existing->owner_id,
                        $existing->reaction_type,
                    ));

                    return $existing;
                }

                $reaction = Reaction::query()->create([
                    'reactor_type' => $actorIdentity['type'],
                    'reactor_id' => $actorIdentity['id'],
                    'reactable_type' => $subjectIdentity['type'],
                    'reactable_id' => $subjectIdentity['id'],
                    'reaction_type' => $reactionType,
                    'status' => 'active',
                    'reacted_at' => CarbonImmutable::now(),
                    'source' => $source,
                    'metadata' => $metadata,
                ]);

                event(new ReactionCreated(
                    $reaction->id,
                    $reaction->reactor_type,
                    $reaction->reactor_id,
                    $reaction->reactable_type,
                    $reaction->reactable_id,
                    $reaction->owner_type,
                    $reaction->owner_id,
                    $reaction->reaction_type,
                ));

                return $reaction;
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $existing = Reaction::query()->forOwner()
                ->where('reactor_type', $actorIdentity['type'])
                ->where('reactor_id', $actorIdentity['id'])
                ->where('reactable_type', $subjectIdentity['type'])
                ->where('reactable_id', $subjectIdentity['id'])
                ->where('reaction_type', $reactionType)
                ->first();

            if ($existing instanceof Reaction && $existing->status === ReactionStatus::Active) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function removeReaction(CanInteract $actor, Reactable $subject, ?string $reactionType = null, array $options = []): void
    {
        $this->assertModels($actor, $subject, Reactable::class);

        (new Reaction)->getConnection()->transaction(function () use ($actor, $subject, $reactionType): void {
            $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
            $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');
            $query = Reaction::query()->forOwner()
                ->where('reactor_type', $actorIdentity['type'])
                ->where('reactor_id', $actorIdentity['id'])
                ->where('reactable_type', $subjectIdentity['type'])
                ->where('reactable_id', $subjectIdentity['id'])
                ->where('status', 'active');

            if ($reactionType) {
                $query->where('reaction_type', $reactionType);
            }

            foreach ($query->lockForUpdate()->get() as $reaction) {
                $reaction->update(['status' => 'removed', 'removed_at' => CarbonImmutable::now()]);
                event(new ReactionRemoved(
                    $reaction->id,
                    $reaction->reactor_type,
                    $reaction->reactor_id,
                    $reaction->reactable_type,
                    $reaction->reactable_id,
                    $reaction->owner_type,
                    $reaction->owner_id,
                    $reaction->reaction_type,
                ));
            }
        });
    }

    private function assertModels(CanInteract $actor, object $subject, string $subjectContract): void
    {
        EngagementModelGuard::assertContract($actor, CanInteract::class, 'actor');
        EngagementModelGuard::assertContract($subject, $subjectContract, 'subject');
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
