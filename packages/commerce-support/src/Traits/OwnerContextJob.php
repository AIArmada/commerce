<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;
use RuntimeException;

trait OwnerContextJob
{
    use SerializesModels;

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

    abstract protected function performJob(): void;

    protected function resolveOwnerContextFromJob(): OwnerJobContext
    {
        if ($this instanceof OwnerScopedJob) {
            return $this->ownerContext();
        }

        $reflection = new \ReflectionObject($this);
        $ownerType = null;
        $ownerId = null;
        $ownerIsGlobal = false;

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
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
