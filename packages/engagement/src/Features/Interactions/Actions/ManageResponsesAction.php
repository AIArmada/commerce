<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Actions;

use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\EngagementPolicyResolver;
use AIArmada\Engagement\Contracts\Respondable;
use AIArmada\Engagement\Enums\ResponseStatus;
use AIArmada\Engagement\Events\ResponseCancelled;
use AIArmada\Engagement\Events\ResponseChanged;
use AIArmada\Engagement\Events\ResponseCreated;
use AIArmada\Engagement\Features\Interactions\Models\Response;
use AIArmada\Engagement\Support\EngagementModelGuard;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;

final readonly class ManageResponsesAction
{
    public function __construct(
        private EngagementPolicyResolver $policy,
    ) {}

    public function respond(CanInteract $actor, Respondable $subject, string $responseType, array $options = []): Response
    {
        $this->assertModels($actor, $subject, Respondable::class);

        $responseType = EngagementModelGuard::requiredString($responseType, 'response_type');
        $visibility = EngagementModelGuard::boundedString(
            $options['visibility'] ?? null,
            'visibility',
        );
        $source = EngagementModelGuard::boundedString($options['source'] ?? null, 'source');
        $metadata = EngagementModelGuard::optionalArray($options['metadata'] ?? null, 'metadata');

        $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
        $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');

        try {
            return (new Response)->getConnection()->transaction(function () use ($actor, $subject, $responseType, $visibility, $source, $metadata, $actorIdentity, $subjectIdentity): Response {
                $this->authorize(
                    $this->policy->canRespond($actor, $subject, $responseType),
                    'Responding to this subject is not authorized.',
                );

                $existing = Response::query()->forOwner()
                    ->where('responder_type', $actorIdentity['type'])
                    ->where('responder_id', $actorIdentity['id'])
                    ->where('respondable_type', $subjectIdentity['type'])
                    ->where('respondable_id', $subjectIdentity['id'])
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof Response && $existing->status !== ResponseStatus::Active) {
                    $existing->update([
                        'response_type' => $responseType,
                        'status' => ResponseStatus::Active,
                        'responded_at' => CarbonImmutable::now(),
                        'visibility' => $visibility ?? $existing->visibility,
                        'changed_at' => null,
                        'cancelled_at' => null,
                    ]);
                    event(new ResponseCreated(
                        $existing->id,
                        $existing->responder_type,
                        $existing->responder_id,
                        $existing->respondable_type,
                        $existing->respondable_id,
                        $existing->owner_type,
                        $existing->owner_id,
                        $existing->response_type,
                    ));

                    return $existing;
                }

                if ($existing instanceof Response) {
                    if ($existing->response_type === $responseType) {
                        if ($visibility !== null && $existing->visibility !== $visibility) {
                            $existing->update([
                                'visibility' => $visibility,
                                'changed_at' => CarbonImmutable::now(),
                            ]);
                        }

                        return $existing;
                    }

                    $oldType = $existing->response_type;
                    $existing->update([
                        'response_type' => $responseType,
                        'status' => ResponseStatus::Active,
                        'visibility' => $visibility ?? $existing->visibility,
                        'changed_at' => CarbonImmutable::now(),
                        'cancelled_at' => null,
                        'metadata' => array_merge(
                            (array) $existing->metadata,
                            ['previous_response_type' => $oldType],
                        ),
                    ]);
                    event(new ResponseChanged(
                        $existing->id,
                        $existing->responder_type,
                        $existing->responder_id,
                        $existing->respondable_type,
                        $existing->respondable_id,
                        $existing->owner_type,
                        $existing->owner_id,
                        $existing->response_type,
                        $oldType,
                    ));

                    return $existing;
                }

                $response = Response::query()->create([
                    'responder_type' => $actorIdentity['type'],
                    'responder_id' => $actorIdentity['id'],
                    'respondable_type' => $subjectIdentity['type'],
                    'respondable_id' => $subjectIdentity['id'],
                    'response_type' => $responseType,
                    'status' => 'active',
                    'visibility' => $visibility ?? EngagementModelGuard::requiredString($subject->defaultResponseVisibility(), 'visibility'),
                    'responded_at' => CarbonImmutable::now(),
                    'source' => $source,
                    'metadata' => $metadata,
                ]);

                event(new ResponseCreated(
                    $response->id,
                    $response->responder_type,
                    $response->responder_id,
                    $response->respondable_type,
                    $response->respondable_id,
                    $response->owner_type,
                    $response->owner_id,
                    $response->response_type,
                ));

                return $response;
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $existing = Response::query()->forOwner()
                ->where('responder_type', $actorIdentity['type'])
                ->where('responder_id', $actorIdentity['id'])
                ->where('respondable_type', $subjectIdentity['type'])
                ->where('respondable_id', $subjectIdentity['id'])
                ->first();

            if ($existing instanceof Response && $existing->status === ResponseStatus::Active && $existing->response_type === $responseType && ($visibility === null || $existing->visibility === $visibility)) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function cancelResponse(CanInteract $actor, Respondable $subject, array $options = []): void
    {
        $this->assertModels($actor, $subject, Respondable::class);

        (new Response)->getConnection()->transaction(function () use ($actor, $subject): void {
            $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
            $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');
            $response = Response::query()->forOwner()
                ->where('responder_type', $actorIdentity['type'])
                ->where('responder_id', $actorIdentity['id'])
                ->where('respondable_type', $subjectIdentity['type'])
                ->where('respondable_id', $subjectIdentity['id'])
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($response) {
                $response->update(['status' => 'cancelled', 'cancelled_at' => CarbonImmutable::now()]);
                event(new ResponseCancelled(
                    $response->id,
                    $response->responder_type,
                    $response->responder_id,
                    $response->respondable_type,
                    $response->respondable_id,
                    $response->owner_type,
                    $response->owner_id,
                    $response->response_type,
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
