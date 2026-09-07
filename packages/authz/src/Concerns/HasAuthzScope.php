<?php

declare(strict_types=1);

namespace AIArmada\Authz\Concerns;

use AIArmada\Authz\Models\AuthzScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @mixin Model
 *
 * @phpstan-require-extends Model
 *
 * @property-read AuthzScope|null $authzScope
 */
trait HasAuthzScope
{
    public static function bootHasAuthzScope(): void
    {
        static::created(function (self $model): void {
            $model->ensureAuthzScope();
        });

        static::updated(function (self $model): void {
            if (! $model->wasChanged($model->getAuthzScopeLabelAttributes())) {
                return;
            }

            $model->syncAuthzScopeLabel();
        });

        static::deleted(function (self $model): void {
            $model->authzScope()->first()?->delete();
        });
    }

    /**
     * @return MorphOne<AuthzScope, $this>
     */
    public function authzScope(): MorphOne
    {
        return $this->morphOne(AuthzScope::class, 'scopeable');
    }

    public function ensureAuthzScope(): AuthzScope
    {
        /** @var AuthzScope $authzScope */
        $authzScope = $this->authzScope()->firstOrCreate([], [
            'label' => $this->getAuthzScopeLabel(),
        ]);

        return $authzScope;
    }

    public function syncAuthzScopeLabel(): void
    {
        $scope = $this->authzScope;
        $label = $this->getAuthzScopeLabel();

        if (! $scope instanceof AuthzScope || $label === $scope->label) {
            return;
        }

        $scope->forceFill(['label' => $label])->save();
    }

    /**
     * Get the attributes that can change the generated scope label.
     *
     * Override this when getAuthzScopeLabel() uses different attributes.
     *
     * @return list<string>
     */
    public function getAuthzScopeLabelAttributes(): array
    {
        return [$this->getKeyName(), 'name'];
    }

    public function getAuthzScopeLabel(): string
    {
        $label = (string) $this->getKey();
        $name = $this->getAttribute('name');

        if (is_string($name) && $name !== '') {
            $label = $name;
        }

        return class_basename($this) . ': ' . $label;
    }
}
