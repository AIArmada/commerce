<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentFeedbackPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /* @phpstan-ignore return.type */
        return filament(app(self::class)->getId());
    }

    public function getId(): string
    {
        return 'filament-feedback';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources($this->getResources())
            ->pages($this->getPages())
            ->widgets($this->getWidgets());
    }

    public function boot(Panel $panel): void {}

    private function getPages(): array
    {
        return [
            Pages\FeedbackDashboard::class,
        ];
    }

    private function getResources(): array
    {
        $r = config('filament-feedback.resources.enabled', []);
        $resources = [];

        if ($r['feedback_form'] ?? true) {
            $resources[] = Resources\FeedbackFormResource::class;
        }
        if ($r['feedback_response'] ?? true) {
            $resources[] = Resources\FeedbackResponseResource::class;
        }
        if ($r['feedback_invitation'] ?? true) {
            $resources[] = Resources\FeedbackInvitationResource::class;
        }
        if ($r['feedback_template'] ?? true) {
            $resources[] = Resources\FeedbackTemplateResource::class;
        }
        if ($r['feedback_testimonial'] ?? true) {
            $resources[] = Resources\FeedbackTestimonialResource::class;
        }

        return $resources;
    }

    private function getWidgets(): array
    {
        return [
            Widgets\FeedbackOverviewWidget::class,
            Widgets\FeedbackResponseTrendWidget::class,
            Widgets\FeedbackAverageRatingWidget::class,
            Widgets\FeedbackNpsWidget::class,
            Widgets\FeedbackCsatWidget::class,
            Widgets\FeedbackRatingDistributionWidget::class,
            Widgets\FeedbackLatestCommentsWidget::class,
            Widgets\FeedbackCompletionRateWidget::class,
            Widgets\FeedbackTestimonialsPendingWidget::class,
        ];
    }
}
