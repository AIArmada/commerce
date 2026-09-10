<?php

declare(strict_types=1);

namespace AIArmada\Communications\Support;

use AIArmada\Communications\Data\CommunicationContextData;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class EventReferenceNormalizer
{
    /**
     * @param  Model|CommunicationContextData|array<string, mixed>|string  $reference
     * @return array{type: string, id: string}
     */
    public function normalize(
        Model | CommunicationContextData | array | string $reference,
        string | int | null $referenceId = null,
    ): array {
        if ($reference instanceof Model) {
            return $this->fromModel($reference);
        }

        if ($reference instanceof CommunicationContextData) {
            return $this->fromValues($reference->subjectType, $reference->subjectId);
        }

        if (is_array($reference)) {
            $embeddedReference = $reference['event'] ?? $reference['reference'] ?? $reference['subject'] ?? null;

            if ($embeddedReference instanceof Model) {
                return $this->fromModel($embeddedReference);
            }

            return $this->fromValues(
                $reference['reference_type']
                    ?? $reference['type']
                    ?? $reference['subject_type']
                    ?? $reference['event_type']
                    ?? null,
                $reference['reference_id']
                    ?? $reference['id']
                    ?? $reference['subject_id']
                    ?? $reference['event_id']
                    ?? null,
            );
        }

        return $this->fromValues($reference, $referenceId);
    }

    /**
     * @return array{type: string, id: string}
     */
    private function fromModel(Model $model): array
    {
        return $this->fromValues($model->getMorphClass(), $model->getKey());
    }

    /**
     * @return array{type: string, id: string}
     */
    private function fromValues(mixed $type, mixed $id): array
    {
        if (! is_string($type) || mb_trim($type) === '') {
            throw new InvalidArgumentException('Communication references require a non-empty reference type.');
        }

        if (! is_string($id) && ! is_int($id)) {
            throw new InvalidArgumentException('Communication references require a string or integer reference ID.');
        }

        $normalizedId = (string) $id;

        if (mb_trim($normalizedId) === '') {
            throw new InvalidArgumentException('Communication references require a non-empty reference ID.');
        }

        return [
            'type' => mb_trim($type),
            'id' => $normalizedId,
        ];
    }
}
