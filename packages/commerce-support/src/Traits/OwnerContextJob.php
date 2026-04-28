<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Trait for queued jobs that require owner context.
 *
 * Automatically enters the owner context when the job runs, preventing
 * cross-tenant data access in a shared-database multitenancy model.
 *
 * Jobs using this trait should include either a public owner-bearing model property,
 * or explicit public `owner_type` + `owner_id` payload fields.
 *
 * @example
 * ```php
 * class SendOrderNotificationJob implements ShouldQueue
 * {
 *     use OwnerContextJob;
 *     use SerializesModels;
 *
 *     public function __construct(
 *         public Order $order,
 *     ) {}
 *
 *     public function handle(): void
 *     {
 *         // OwnerContextJob automatically enters OwnerContext::withOwner($owner, ...)
 *         // Query and access is owner-scoped and safe
 *         $order = Order::find($this->order->id); // Scoped to current owner
 *     }
 * }
 * ```
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
        $owner = $this->resolveOwnerFromJob();

        if (config('commerce-support.owner.enabled', false) && $owner === null) {
            throw new RuntimeException(
                sprintf(
                    '%s requires an owner context (owner_type + owner_id). ' .
                    'Ensure the job payload includes a model with owner data or set owner_type/owner_id explicitly.',
                    static::class
                )
            );
        }

        OwnerContext::withOwner($owner, function (): void {
            $this->performJob();
        });
    }

    /**
     * Perform the actual job logic within owner context.
     *
     * Override this method in your job class instead of `handle()`.
     *
     * @example
     * ```php
     * public function performJob(): void
     * {
     *     $this->order->markAsProcessed();
     * }
     * ```
     */
    abstract protected function performJob(): void;

    /**
     * Resolve the owner model from the job payload.
     *
     * Looks for properties that are Eloquent models or have `owner_type`/`owner_id` attributes.
     * Override this method if your job's owner data is structured differently.
     *
     * @return Model|null
     */
    protected function resolveOwnerFromJob(): ?Model
    {
        $reflection = new \ReflectionClass($this);
        $ownerType = null;
        $ownerId = null;

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $value = $property->getValue($this);

            if ($property->getName() === 'owner_type' && is_string($value) && $value !== '') {
                $ownerType = $value;
            }

            if ($property->getName() === 'owner_id' && (is_string($value) || is_int($value)) && $value !== '') {
                $ownerId = $value;
            }

            if ($value instanceof Model) {
                if (method_exists($value, 'getOwner')) {
                    return $value->getOwner();
                }

                if (method_exists($value, 'getAttribute')) {
                    $ownerType = $value->getAttribute('owner_type');
                    $ownerId = $value->getAttribute('owner_id');

                    if ($ownerType !== null && $ownerId !== null) {
                        return OwnerContext::fromTypeAndId($ownerType, $ownerId);
                    }
                }

                if (method_exists($value, 'getMorphClass')) {
                    return $value;
                }
            }
        }

        if ($ownerType !== null && $ownerId !== null) {
            return OwnerContext::fromTypeAndId($ownerType, $ownerId);
        }

        return null;
    }
}
