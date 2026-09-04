<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Enums\EventRegistrationQuestionStatus;
use AIArmada\Events\Enums\EventRegistrationQuestionType;
use AIArmada\Events\Events\EventRegistrationQuestionCreated;
use AIArmada\Events\Models\EventRegistrationQuestion;
use AIArmada\Events\Support\EventWriteGuard;
use AIArmada\Events\Support\ModelResolver;
use AIArmada\Events\Support\Normalization\EventContentNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateEventRegistrationQuestionAction
{
    public function __construct(
        private readonly EventRegistrationScopeResolver $scopeResolver,
        private readonly EventContentNormalizer $contentNormalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Model $target, array $attributes = []): EventRegistrationQuestion
    {
        $scope = $this->scopeResolver->resolve($target);

        $normalized = $this->normalizeAttributes($attributes);
        $scopeAttributes = [
            'event_id' => $scope->event->getKey(),
            'event_occurrence_id' => $scope->occurrence?->getKey(),
            'event_session_id' => $scope->session?->getKey(),
        ];

        $questionClass = ModelResolver::registrationQuestionClass();

        return DB::transaction(function () use ($normalized, $questionClass, $scope, $scopeAttributes): EventRegistrationQuestion {
            EventWriteGuard::findOrFail($scope->event->getKey());

            // Keep this portable across nullable occurrence/session columns.
            // Serialising writes through the event row prevents two concurrent
            // requests from passing the duplicate check together.
            $scope->event::query()
                ->whereKey($scope->event->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $alreadyExists = $questionClass::query()
                ->where($scopeAttributes)
                ->where('field_key', $normalized['field_key'])
                ->exists();

            if ($alreadyExists) {
                throw new InvalidArgumentException('A question with this field key already exists in this scope.');
            }

            /** @var EventRegistrationQuestion $question */
            $question = $questionClass::query()->create(array_merge(
                $scopeAttributes,
                $normalized,
                [
                    'status' => EventRegistrationQuestionStatus::Active->value,
                    'archived_at' => null,
                ],
            ));

            event(new EventRegistrationQuestionCreated($question));

            return $question;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeAttributes(array $attributes): array
    {
        $question = blank($attributes['question'] ?? null)
            ? throw new InvalidArgumentException('Question text is required.')
            : $this->contentNormalizer->normalizeTitle((string) $attributes['question']);

        if (mb_strlen($question) > 500) {
            throw new InvalidArgumentException('Question text must not exceed 500 characters.');
        }

        $fieldKey = $this->normalizeFieldKey($attributes['field_key'] ?? null, $question);
        $type = $this->resolveType($attributes['type'] ?? null);
        $options = $this->normalizeOptions($attributes['options'] ?? null, $type);

        return [
            'field_key' => $fieldKey,
            'question' => $question,
            'description' => $this->normalizeDescription($attributes['description'] ?? null),
            'type' => $type->value,
            'options' => $options,
            'is_required' => (bool) ($attributes['is_required'] ?? false),
            'order_column' => isset($attributes['order_column']) ? (int) $attributes['order_column'] : null,
            'metadata' => is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : null,
        ];
    }

    private function normalizeFieldKey(mixed $value, string $question): string
    {
        $candidate = blank($value) ? $question : (string) $value;
        $fieldKey = Str::of($candidate)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->substr(0, 80)
            ->value();

        if ($fieldKey === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $fieldKey)) {
            throw new InvalidArgumentException('Question field key must start with a letter and contain only letters, numbers, and underscores.');
        }

        return $fieldKey;
    }

    private function resolveType(mixed $value): EventRegistrationQuestionType
    {
        $type = EventRegistrationQuestionType::tryFrom((string) ($value ?? EventRegistrationQuestionType::Text->value));

        if ($type === null) {
            throw new InvalidArgumentException('The selected question type is not supported.');
        }

        return $type;
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
