<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Resources\FeedbackFormResource\Pages;

use AIArmada\Feedback\Analytics\FeedbackAnalyticsService;
use AIArmada\Feedback\Data\FeedbackAnalyticsData;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\FilamentFeedback\Resources\FeedbackFormResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

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
        return app(FeedbackAnalyticsService::class)->summaryForForm($this->getForm());
    }

    /**
     * @return Collection<int, mixed>
     */
    public function getLatestComments(): Collection
    {
        return app(FeedbackAnalyticsService::class)->latestComments($this->getForm());
    }

    private function getForm(): FeedbackForm
    {
        $record = $this->getRecord();

        abort_unless($record instanceof FeedbackForm, 404);

        return $record;
    }
}
