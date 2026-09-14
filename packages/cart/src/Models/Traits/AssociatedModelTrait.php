<?php

declare(strict_types=1);

namespace AIArmada\Cart\Models\Traits;

trait AssociatedModelTrait
{
    /**
     * Check if item is associated with a model
     */
    public function isAssociatedWith(string $modelClass): bool
    {
        if (is_string($this->associatedModel)) {
            return $this->associatedModel === $modelClass;
        }
        if (is_object($this->associatedModel)) {
            return $this->associatedModel instanceof $modelClass;
        }

        return false;
    }

    /**
     * Get associated model instance
     */
    public function getAssociatedModel(): object | string | null
    {
        return $this->associatedModel;
    }

    /**
     * Get associated model as array representation.
     *
     * Only the class+id reference is persisted: restore re-fetches the model
     * from the database, so an embedded snapshot would only bloat stored JSON
     * toward the size cap while going stale (and possibly leaking attributes).
     *
     * @return array<string, mixed>|string|null
     */
    private function getAssociatedModelArray(): array | string | null
    {
        if (is_string($this->associatedModel)) {
            return $this->associatedModel;
        }
        if (is_object($this->associatedModel)) {
            return [
                'class' => get_class($this->associatedModel),
                'id' => $this->associatedModel->id ?? null,
            ];
        }

        return null;
    }
}
