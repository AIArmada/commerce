<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Enums\EventRegistrationQuestionType;
use AIArmada\Events\Events\EventRegistrationQuestionUpdated;
use AIArmada\Events\Models\EventRegistrationQuestion;
use AIArmada\Events\Support\EventWriteGuard;
use AIArmada\Events\Support\Normalization\EventContentNormalizer;
use InvalidArgumentException;

final class UpdateEventRegistrationQuestionAction
{
    public function __construct(
        private readonly EventContentNormalizer $contentNormalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{changes: array<string, array{old: mixed, new: mixed}>, question: EventRegistrationQuestion}
     */
    public function handle(EventRegistrationQuestion $question, array $attributes): array
    {
        EventWriteGuard::findOrFail($question->event_id);

        $original = $question->getRawOriginal();
        $allowed = array_intersect_key($attributes, array_flip([
            'question',
            'description',
            'type',
            'options',
            'is_required',
            'order_column',
            'metadata',
        ]));

        if (array_key_exists('question', $allowed)) {
            if (blank($allowed['question'])) {
                throw new InvalidArgumentException('Question text is required.');
            }

            $allowed['question'] = $this->contentNormalizer->normalizeTitle((string) $allowed['question']);

            if (mb_strlen($allowed['question']) > 500) {
                throw new InvalidArgumentException('Question text must not exceed 500 characters.');
            }
        }

        $type = array_key_exists('type', $allowed)
            ? EventRegistrationQuestionType::tryFrom((string) $allowed['type'])
            : $question->type;

        if ($type === null) {
            throw new InvalidArgumentException('The selected question type is not supported.');
        }

        if (array_key_exists('type', $allowed)) {
            $allowed['type'] = $type->value;
        }

        if (array_key_exists('options', $allowed)) {
            $allowed['options'] = $this->normalizeOptions($allowed['options'], $type);
        } elseif (array_key_exists('type', $allowed)) {
            $allowed['options'] = $this->normalizeOptions($question->options, $type);
        }

        if (array_key_exists('description', $allowed)) {
            $allowed['description'] = $this->normalizeDescription($allowed['description']);
        }

        if (array_key_exists('is_required', $allowed)) {
            $allowed['is_required'] = (bool) $allowed['is_required'];
        }

        if (array_key_exists('metadata', $allowed) && ! is_array($allowed['metadata'])) {
            $allowed['metadata'] = null;
        }

        $question->update($allowed);

        $current = $question->getAttributes();

        $changes = [];
        foreach ($allowed as $key => $newValue) {
            $oldValue = $original[$key] ?? null;
            $normalizedNewValue = $current[$key] ?? null;

            if ($oldValue !== $normalizedNewValue) {
                $changes[$key] = ['old' => $oldValue, 'new' => $normalizedNewValue];
            }
        }

        if ($changes !== []) {
            event(new EventRegistrationQuestionUpdated($question, $changes));
        }

        return [
            'changes' => $changes,
            'question' => $question->fresh(),
        ];
    }

    /**
     * @return array<int, string>|null
     */
    private function normalizeOptions(mixed $value, EventRegistrationQuestionType $type): ?array
    {
        $options = [];

        if (is_array($value)) {
            foreach ($value as $option) {
                if (is_string($option) || is_numeric($option)) {
                    $label = mb_trim((string) $option);
                } elseif (is_array($option) && isset($option['value'])) {
                    $label = mb_trim((string) $option['value']);
                } else {
                    continue;
                }

                if ($label !== '' && ! in_array($label, $options, true)) {
                    $options[] = $label;
                }
            }
        }

        if ($type->requiresOptions() && $options === []) {
            throw new InvalidArgumentException('Choice questions require at least one option.');
        }

        return $options === [] ? null : $options;
    }

    private function normalizeDescription(mixed $value): ?string
    {
        if ($value === null || ! is_scalar($value)) {
            return null;
        }

        $description = mb_trim((string) $value);

        return $description === '' ? null : $description;
    }
}
