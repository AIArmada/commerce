<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Forms\EntryPoints\Filament\Resources\FeedbackFormResource\Pages;

use AIArmada\Feedback\Enums\FeedbackFormPurpose;
use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Enums\FeedbackFormVisibility;
use AIArmada\Feedback\Features\Forms\Actions\UpdateFeedbackFormAction;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\FilamentFeedback\Features\Forms\EntryPoints\Filament\Resources\FeedbackFormResource;
use AIArmada\FilamentFeedback\Features\Forms\EntryPoints\Filament\Resources\FeedbackFormResource\RelationManagers\QuestionsRelationManager;
use AIArmada\FilamentFeedback\Features\Forms\EntryPoints\Filament\Resources\FeedbackFormResource\RelationManagers\SectionsRelationManager;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

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
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('responses')->url(fn (): string => static::getResource()::getUrl('responses', ['record' => $this->getRecord()])),
            Action::make('invitations')->url(fn (): string => static::getResource()::getUrl('invitations', ['record' => $this->getRecord()])),
            Action::make('testimonials')->url(fn (): string => static::getResource()::getUrl('testimonials', ['record' => $this->getRecord()])),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof FeedbackForm, 404);

        return app(UpdateFeedbackFormAction::class)->execute($record, $data);
    }
}
