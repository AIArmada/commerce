<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Actions;

use AIArmada\Events\Data\EventRegistrationQuestionType;
use AIArmada\Events\Events\EventRegistrationQuestionUpdated;
use AIArmada\Events\Features\Registrations\Models\EventRegistrationQuestion;
use AIArmada\Events\Platform\EventContentNormalizer;
use AIArmada\Events\Platform\EventWriteGuard;
use InvalidArgumentException;

final class UpdateEventRegistrationQuestionAction
{
    public function __construct(
        private readonly EventContentNormalizer $contentNormalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function handle(string $questionId, array $attributes): array
    {
        /** @var EventRegistrationQuestion $question */
        $question = EventRegistrationQuestion::query()->whereKey($questionId)->firstOrFail();

        EventWriteGuard::findOrFail((string) $question->event_id);

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
            event(new EventRegistrationQuestionUpdated(
                (string) $question->getKey(),
                (string) $question->event_id,
                $question->event_occurrence_id !== null ? (string) $question->event_occurrence_id : null,
                $question->event_session_id !== null ? (string) $question->event_session_id : null,
                $changes,
            ));
        }

        return $changes;
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
