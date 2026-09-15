<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

final class HolderAttributesValidator
{
    /**
     * Validate user-supplied holder attributes (issuance + transfer paths).
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidArgumentException
     * @throws AuthorizationException
     */
    public static function validateAttributes(array $attributes): void
    {
        if (array_key_exists('name', $attributes) && $attributes['name'] !== null) {
            if (! is_string($attributes['name']) || mb_strlen($attributes['name']) > 255) {
                throw new InvalidArgumentException('Holder name must be a string of at most 255 characters.');
            }
        }

        if (array_key_exists('email', $attributes) && $attributes['email'] !== null && $attributes['email'] !== '') {
            if (! is_string($attributes['email'])
                || mb_strlen($attributes['email']) > 255
                || filter_var($attributes['email'], FILTER_VALIDATE_EMAIL) === false
            ) {
                throw new InvalidArgumentException('Holder email must be a valid email address of at most 255 characters.');
            }
        }

        $hasType = array_key_exists('holder_type', $attributes) && $attributes['holder_type'] !== null;
        $hasId = array_key_exists('holder_id', $attributes) && $attributes['holder_id'] !== null;

        if ($hasType xor $hasId) {
            throw new InvalidArgumentException('Holder type and holder id must be provided together.');
        }

        if ($hasType && $hasId) {
            self::validateHolderReference($attributes['holder_type'], $attributes['holder_id']);
        }
    }

    /**
     * Validate an already-resolved holder model link (no cross-tenant links).
     *
     * @throws InvalidArgumentException
     * @throws AuthorizationException
     */
    public static function validateHolderModel(Model $model): void
    {
        if (! $model->exists || $model->getKey() === null) {
            throw new InvalidArgumentException('The holder model must be persisted before it can be linked.');
        }

        self::assertTypeAllowed($model->getMorphClass(), $model::class);

        $visible = $model::class::query()->whereKey($model->getKey())->exists();

        if (! $visible) {
            throw new AuthorizationException('The holder is not accessible in the current owner scope.');
        }
    }

    /**
     * @throws InvalidArgumentException
     * @throws AuthorizationException
     */
    private static function validateHolderReference(mixed $holderType, mixed $holderId): void
    {
        if (! is_string($holderType) || $holderType === '' || (! is_string($holderId) && ! is_int($holderId))) {
            throw new InvalidArgumentException('Holder type must be a non-empty string and holder id a string or integer.');
        }

        $relatedClass = Relation::getMorphedModel($holderType) ?? $holderType;

        self::assertTypeAllowed($holderType, is_string($relatedClass) ? $relatedClass : null);

        if (! is_string($relatedClass) || ! class_exists($relatedClass) || ! is_a($relatedClass, Model::class, true)) {
            throw new InvalidArgumentException(sprintf('Holder type "%s" does not resolve to a model.', $holderType));
        }

        /** @var class-string<Model> $relatedClass */
        $related = $relatedClass::query()->whereKey($holderId)->first();

        if (! $related instanceof Model) {
            throw new AuthorizationException('The holder is not accessible in the current owner scope.');
        }
    }

    /**
     * Enforce the holder allowlist against both the morph alias and the
     * resolved class name. An empty allowlist leaves both paths open,
     * matching the documented opt-in convention.
     */
    private static function assertTypeAllowed(string $morphClass, ?string $class): void
    {
        $allowedTypes = config('ticketing.holders.allowed_types', []);

        if (! is_array($allowedTypes) || $allowedTypes === []) {
            return;
        }

        if (in_array($morphClass, $allowedTypes, true)) {
            return;
        }

        if ($class !== null && in_array($class, $allowedTypes, true)) {
            return;
        }

        throw new InvalidArgumentException(sprintf('Holder type "%s" is not allowed.', $morphClass));
    }
}
