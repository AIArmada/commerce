<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;
use ReflectionObject;
use ReflectionProperty;
use RuntimeException;

/**
 * Trait for queued jobs that require owner context.
 *
 * Automatically enters the owner context when the job runs, preventing
 * cross-tenant data access in a shared-database multitenancy model.
 *
 * Jobs using this trait should include either:
 * - a public owner-bearing model property,
 * - explicit public `ownerType` / `ownerId` (or snake_case equivalents) payload fields,
 * - or implement {@see OwnerScopedJob} for fully explicit owner context payloads.
 */
trait OwnerContextJob
{
    use SerializesModels;

    /**
     * Execute the job within owner context.
     *
     * This method wraps the actual job handler in `OwnerContext::withOwner(...)`.
     * Subclasses should implement `performJob()` instead of `handle()`.
     *
     * @internal Framework integration; do not override without understanding owner scoping.
     */
    final public function handle(): void
    {
        $ownerContext = $this->resolveOwnerContextFromJob();

        if ($ownerContext->isExplicitGlobal()) {
            OwnerContext::withOwner(null, function (): void {
                $this->performJob();
            });

            return;
        }

        $owner = $ownerContext->toOwnerModel();

        if (config('commerce-support.owner.enabled', false) && $owner === null) {
            throw new RuntimeException(
                sprintf(
                    '%s requires an owner context (ownerType + ownerId). Ensure the job payload includes owner data or an explicit global owner context.',
                    static::class,
                ),
            );
        }

        OwnerContext::withOwner($owner, function (): void {
            $this->performJob();
        });
    }

    /**
     * Perform the actual job logic within owner context.
     */
    abstract protected function performJob(): void;

    /**
     * Resolve owner context from the job payload.
     *
     * If the job implements {@see OwnerScopedJob}, that explicit context is used.
     * Otherwise, the trait inspects public properties for owner-bearing models,
     * `ownerType` / `ownerId` pairs, and explicit-global flags.
     */
    protected function resolveOwnerContextFromJob(): OwnerJobContext
    {
        if ($this instanceof OwnerScopedJob) {
            return $this->ownerContext();
        }

        $reflection = new ReflectionObject($this);
        $ownerType = null;
        $ownerId = null;
        $ownerIsGlobal = false;

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $value = $property->getValue($this);
            $propertyName = $property->getName();

            if (($propertyName === 'ownerType' || $propertyName === 'owner_type') && is_string($value) && $value !== '') {
                $ownerType = $value;
            }

            if (($propertyName === 'ownerId' || $propertyName === 'owner_id') && (is_string($value) || is_int($value)) && $value !== '') {
                $ownerId = $value;
            }

            if (($propertyName === 'ownerIsGlobal' || $propertyName === 'owner_is_global') && $value === true) {
                $ownerIsGlobal = true;
            }

            if ($value instanceof Model) {
                if (method_exists($value, 'getOwner')) {
                    $owner = $value->getOwner();

                    if ($owner instanceof Model) {
                        return OwnerJobContext::fromOwnerModel($owner);
                    }
                }

                if (method_exists($value, 'getAttribute')) {
                    $modelOwnerType = $value->getAttribute('owner_type');
                    $modelOwnerId = $value->getAttribute('owner_id');

                    if (is_string($modelOwnerType) && $modelOwnerType !== '' && (is_string($modelOwnerId) || is_int($modelOwnerId))) {
                        return new OwnerJobContext(
                            ownerType: $modelOwnerType,
                            ownerId: $modelOwnerId,
                            ownerIsGlobal: false,
                        );
                    }
                }

                if (method_exists($value, 'getMorphClass')) {
                    return OwnerJobContext::fromOwnerModel($value);
                }
            }
        }

        try {
            return new OwnerJobContext(
                ownerType: $ownerType,
                ownerId: $ownerId,
                ownerIsGlobal: $ownerIsGlobal,
            );
        } catch (InvalidArgumentException $exception) {
            throw new RuntimeException(
                sprintf('%s received invalid owner job context payload: %s', static::class, $exception->getMessage()),
                previous: $exception,
            );
        }
    }
}
