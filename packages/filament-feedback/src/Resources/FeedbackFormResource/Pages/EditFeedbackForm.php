<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Resources\FeedbackFormResource\Pages;

use AIArmada\Feedback\Enums\FeedbackFormPurpose;
use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Enums\FeedbackFormVisibility;
use AIArmada\FilamentFeedback\Resources\FeedbackFormResource;
use AIArmada\FilamentFeedback\Resources\FeedbackFormResource\RelationManagers\InvitationsRelationManager;
use AIArmada\FilamentFeedback\Resources\FeedbackFormResource\RelationManagers\QuestionsRelationManager;
use AIArmada\FilamentFeedback\Resources\FeedbackFormResource\RelationManagers\ResponsesRelationManager;
use AIArmada\FilamentFeedback\Resources\FeedbackFormResource\RelationManagers\SectionsRelationManager;
use AIArmada\FilamentFeedback\Resources\FeedbackFormResource\RelationManagers\TestimonialsRelationManager;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

final class EditFeedbackForm extends EditRecord
{
    protected static string $resource = FeedbackFormResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->maxLength(255),
                Select::make('purpose')
                    ->options(FeedbackFormPurpose::options())
                    ->required(),
                Select::make('status')
                    ->options(FeedbackFormStatus::options()),
                Select::make('visibility')
                    ->options(FeedbackFormVisibility::options()),
                Toggle::make('is_anonymous_allowed'),
                Toggle::make('is_anonymity_optional'),
                Toggle::make('is_login_required'),
                Toggle::make('is_one_response_per_respondent'),
                Toggle::make('is_edit_after_submit_allowed'),
                DateTimePicker::make('opens_at'),
                DateTimePicker::make('closes_at'),
            ]);
    }

    public function getRelationManagers(): array
    {
        return [
            SectionsRelationManager::class,
            QuestionsRelationManager::class,
            ResponsesRelationManager::class,
            InvitationsRelationManager::class,
            TestimonialsRelationManager::class,
        ];
    }
}
