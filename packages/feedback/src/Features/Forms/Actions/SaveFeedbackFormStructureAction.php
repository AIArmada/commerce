<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Forms\Models\FeedbackQuestion;
use AIArmada\Feedback\Features\Forms\Models\FeedbackQuestionOption;
use AIArmada\Feedback\Features\Forms\Models\FeedbackSection;
use AIArmada\Feedback\Support\QuestionTypeRegistry;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class SaveFeedbackFormStructureAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function saveSection(string $formId, array $data, ?FeedbackSection $section = null): FeedbackSection
    {
        return (new FeedbackForm)->getConnection()->transaction(function () use ($formId, $data, $section): FeedbackSection {
            $this->findOwned(FeedbackForm::class, $formId, lock: true);

            if ($section !== null) {
                $section = $this->findOwned(FeedbackSection::class, $section->id);

                if ($section->feedback_form_id !== $formId) {
                    throw new InvalidArgumentException('The feedback section does not belong to the selected form.');
                }
            }

            $section ??= new FeedbackSection;
            $section->fill([
                'feedback_form_id' => $formId,
                'key' => $data['key'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'order_column' => (int) ($data['order_column'] ?? 0),
                'settings' => $data['settings'] ?? [],
                'metadata' => $data['metadata'] ?? [],
            ])->save();

            return $section;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveQuestion(string $formId, array $data, ?FeedbackQuestion $question = null): FeedbackQuestion
    {
        return (new FeedbackForm)->getConnection()->transaction(function () use ($formId, $data, $question): FeedbackQuestion {
            $this->findOwned(FeedbackForm::class, $formId, lock: true);

            if ($question !== null) {
                $question = $this->findOwned(FeedbackQuestion::class, $question->id);

                if ($question->feedback_form_id !== $formId) {
                    throw new InvalidArgumentException('The feedback question does not belong to the selected form.');
                }
            }

            $type = (string) ($data['type'] ?? '');

            if (! QuestionTypeRegistry::isTypeAvailable($type)) {
                throw new InvalidArgumentException("The '{$type}' question type is not available.");
            }

            $key = (string) ($data['key'] ?? '');

            $keyQuery = FeedbackQuestion::query()
                ->where('feedback_form_id', $formId)
                ->where('key', $key);

            if ($question !== null) {
                $keyQuery->where('id', '!=', $question->id);
            }

            $duplicateKey = $keyQuery->exists();

            if ($duplicateKey) {
                throw new InvalidArgumentException("The '{$key}' question key is already used in this form.");
            }

            $sectionId = array_key_exists('feedback_section_id', $data)
                ? $data['feedback_section_id']
                : null;

            if ($sectionId !== null) {
                $section = $this->findOwned(FeedbackSection::class, (string) $sectionId);

                if ($section->feedback_form_id !== $formId) {
                    throw new InvalidArgumentException('The feedback section does not belong to the selected form.');
                }
            }

            $question ??= new FeedbackQuestion;
            $question->fill([
                'feedback_form_id' => $formId,
                'feedback_section_id' => $sectionId,
                'key' => $data['key'],
                'type' => $data['type'],
                'label' => $data['label'],
                'description' => $data['description'] ?? null,
                'help_text' => $data['help_text'] ?? null,
                'placeholder' => $data['placeholder'] ?? null,
                'is_required' => (bool) ($data['is_required'] ?? false),
                'is_scored' => (bool) ($data['is_scored'] ?? false),
                'order_column' => (int) ($data['order_column'] ?? 0),
                'validation_rules' => $data['validation_rules'] ?? [],
                'visibility_rules' => $data['visibility_rules'] ?? [],
                'scoring_rules' => $data['scoring_rules'] ?? [],
                'settings' => $data['settings'] ?? [],
                'metadata' => $data['metadata'] ?? [],
            ])->save();

            return $question;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveOption(
        string $questionId,
        array $data,
        ?FeedbackQuestionOption $option = null,
    ): FeedbackQuestionOption {
        return (new FeedbackQuestion)->getConnection()->transaction(function () use ($questionId, $data, $option): FeedbackQuestionOption {
            $this->findOwned(FeedbackQuestion::class, $questionId, lock: true);

            if ($option !== null) {
                $option = $this->findOwned(FeedbackQuestionOption::class, $option->id);

                if ($option->feedback_question_id !== $questionId) {
                    throw new InvalidArgumentException('The feedback option does not belong to the selected question.');
                }
            }

            $option ??= new FeedbackQuestionOption;
            $option->fill([
                'feedback_question_id' => $questionId,
                'label' => $data['label'],
                'value' => $data['value'],
                'score' => $data['score'] ?? null,
                'order_column' => (int) ($data['order_column'] ?? 0),
                'metadata' => $data['metadata'] ?? [],
            ])->save();

            return $option;
        });
    }

    /**
     * @template TModel of FeedbackForm|FeedbackSection|FeedbackQuestion|FeedbackQuestionOption
     * @param  class-string<TModel>  $modelClass
     * @return TModel
     */
    private function findOwned(string $modelClass, string $id, bool $lock = false): Model
    {
        if ($modelClass::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner($modelClass, $id);
        }

        $query = $modelClass::query()->whereKey($id);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }
}
