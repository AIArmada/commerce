<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Actions;

use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\EngagementPolicyResolver;
use AIArmada\Engagement\Contracts\Shareable;
use AIArmada\Engagement\Contracts\ShareUrlGenerator;
use AIArmada\Engagement\Enums\ShareStatus;
use AIArmada\Engagement\Events\ShareCompleted;
use AIArmada\Engagement\Events\ShareCreated;
use AIArmada\Engagement\Features\Interactions\Models\Share;
use AIArmada\Engagement\Support\EngagementModelGuard;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

final readonly class CreateShareAction
{
    public function __construct(
        private EngagementPolicyResolver $policy,
        private ShareUrlGenerator $shareUrlGenerator,
    ) {}

    public function share(CanInteract $actor, Shareable $subject, array $options = []): Share
    {
        $this->assertModels($actor, $subject, Shareable::class);

        $channel = EngagementModelGuard::boundedString($options['channel'] ?? null, 'channel');
        $destination = EngagementModelGuard::boundedString($options['destination'] ?? null, 'destination');
        $message = EngagementModelGuard::boundedString($options['message'] ?? null, 'message', EngagementModelGuard::TEXT_MAX_LENGTH);
        $metadata = EngagementModelGuard::optionalArray($options['metadata'] ?? null, 'metadata');
        $explicitToken = EngagementModelGuard::boundedString($options['token'] ?? null, 'token');
        $token = ($explicitToken === null || $explicitToken === '') ? Str::random(32) : $explicitToken;

        return (new Share)->getConnection()->transaction(function () use ($actor, $subject, $options, $channel, $destination, $message, $metadata, $token): Share {
            $this->authorize(
                $this->policy->canShare($actor, $subject),
                'Sharing this subject is not authorized.',
            );

            $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
            $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');
            $share = Share::query()->create([
                'sharer_type' => $actorIdentity['type'],
                'sharer_id' => $actorIdentity['id'],
                'shareable_type' => $subjectIdentity['type'],
                'shareable_id' => $subjectIdentity['id'],
                'channel' => $channel,
                'destination' => $destination,
                'share_token' => $token,
                'message' => $message,
                'status' => ShareStatus::Created,
                'share_intent_at' => CarbonImmutable::now(),
                'metadata' => $metadata,
            ]);

            event(new ShareCreated(
                $share->id,
                $share->sharer_type,
                $share->sharer_id,
                $share->shareable_type,
                $share->shareable_id,
                $share->owner_type,
                $share->owner_id,
                $share->channel,
            ));

            if ($options['complete'] ?? true) {
                $shareUrl = $this->shareUrlGenerator->generateShareUrl($subject, array_merge($options, ['token' => $token]));
                $share->update([
                    'share_url' => $shareUrl,
                    'status' => ShareStatus::Shared,
                    'shared_at' => CarbonImmutable::now(),
                ]);
                event(new ShareCompleted(
                    $share->id,
                    $share->sharer_type,
                    $share->sharer_id,
                    $share->shareable_type,
                    $share->shareable_id,
                    $share->owner_type,
                    $share->owner_id,
                    $share->channel,
                ));
            }

            return $share;
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
}
