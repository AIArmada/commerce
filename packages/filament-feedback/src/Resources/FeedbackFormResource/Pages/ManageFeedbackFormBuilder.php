<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Resources\FeedbackFormResource\Pages;

use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\FilamentFeedback\Resources\FeedbackFormResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

final class ManageFeedbackFormBuilder extends Page
{
    use InteractsWithRecord;

    protected static string $resource = FeedbackFormResource::class;

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
    }

    public function getView(): string
    {
        return 'filament-feedback::pages.feedback-form-builder';
    }

    /**
     * @return array{sections: int, questions: int, edit_url: string}
     */
    public function getBuilderSummary(): array
    {
        $form = $this->getForm();

        return [
            'sections' => $form->sections()->count(),
            'questions' => $form->questions()->count(),
            'edit_url' => static::getResource()::getUrl('edit', ['record' => $form]),
        ];
    }

    private function getForm(): FeedbackForm
    {
        $record = $this->getRecord();

        abort_unless($record instanceof FeedbackForm, 404);

        return $record;
    }
}
