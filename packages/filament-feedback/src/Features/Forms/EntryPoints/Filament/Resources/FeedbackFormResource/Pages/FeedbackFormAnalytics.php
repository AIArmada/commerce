<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Forms\EntryPoints\Filament\Resources\FeedbackFormResource\Pages;

use AIArmada\Feedback\Data\FeedbackAnalyticsData;
use AIArmada\Feedback\Features\Analytics\Queries\FeedbackAnalyticsQuery;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\FilamentFeedback\Features\Forms\EntryPoints\Filament\Resources\FeedbackFormResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

final class FeedbackFormAnalytics extends Page
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
        abort_unless(static::getResource()::canView($this->getRecord()), 403);
    }

    public function getView(): string
    {
        return 'filament-feedback::pages.feedback-form-analytics';
    }

    public function getAnalytics(): FeedbackAnalyticsData
    {
        return app(FeedbackAnalyticsQuery::class)->summaryForForm($this->getForm());
    }

    /**
     * @return list<array{id: string, text_value: string}>
     */
    public function getLatestComments(): array
    {
        return app(FeedbackAnalyticsQuery::class)->latestComments($this->getForm());
    }

    private function getForm(): FeedbackForm
    {
        $record = $this->getRecord();

        abort_unless($record instanceof FeedbackForm, 404);

        return $record;
    }
}
