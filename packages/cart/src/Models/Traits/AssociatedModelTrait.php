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
    public function getAssociatedModel(): object|string|null
    {
        return $this->associatedModel;
    }

    /**
     * Get associated model as array representation
     *
     * @return array<string, mixed>|string|null
     */
    private function getAssociatedModelArray(): array|string|null
    {
        if (is_string($this->associatedModel)) {
            return $this->associatedModel;
        }
        if (is_object($this->associatedModel)) {
            return [
                'class' => get_class($this->associatedModel),
                'id' => $this->associatedModel->id ?? null,
                'data' => method_exists($this->associatedModel, 'toArray') ? $this->associatedModel->toArray() : (array) $this->associatedModel,
            ];
        }

        return null;
    }
}
