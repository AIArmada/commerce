<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentFeedback\FilamentFeedbackTestCase;
use AIArmada\CommerceSupport\Persistence\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentFeedback\Features\Analytics\Queries\FeedbackDashboardMemo;
use AIArmada\FilamentFeedback\Features\Forms\EntryPoints\Filament\Resources\FeedbackFormResource;
use AIArmada\FilamentFeedback\Features\Invitations\EntryPoints\Filament\Resources\FeedbackInvitationResource;
use AIArmada\FilamentFeedback\Features\Responses\EntryPoints\Filament\Exports\FeedbackResponsesExport;
use AIArmada\FilamentFeedback\Features\Responses\EntryPoints\Filament\Resources\FeedbackResponseResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

uses(FilamentFeedbackTestCase::class);

it('registers the analytics, builder, and comments views', function (): void {
    expect(View::exists('filament-feedback::pages.feedback-form-analytics'))->toBeTrue()
        ->and(View::exists('filament-feedback::pages.feedback-form-builder'))->toBeTrue()
        ->and(View::exists('filament-feedback::widgets.feedback-latest-comments'))->toBeTrue();
});

it('shares one dashboard computation across widgets within a request', function (): void {
    $memo = app(FeedbackDashboardMemo::class);

    $first = $memo->dashboard();

    OwnerCache::forgetOwner(OwnerContext::resolve());

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $second = $memo->dashboard();
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
    }

    expect($second)->toBe($first)
        ->and($queries)->toBeEmpty();
});

it('leaves response counting to the table column', function (): void {
    expect(FeedbackFormResource::getEloquentQuery()->toSql())->not->toContain('responses');
});

it('keeps network identifiers out of the response export', function (): void {
    $columns = collect(FeedbackResponsesExport::getColumns())
        ->map(static fn ($column): string => $column->getName())
        ->all();

    expect($columns)->not->toContain('ip_address');
});

it('projects the parent form name on invitation and response tables without ORM relationships', function (): void {
    expect(FeedbackInvitationResource::getEloquentQuery()->toSql())->toContain('form_name')
        ->and(FeedbackResponseResource::getEloquentQuery()->toSql())->toContain('form_name');
});
