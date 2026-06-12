<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Resources\FeedbackFormResource\Pages;

use AIArmada\Feedback\Actions\CreateFeedbackFormAction;
use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\Feedback\Enums\FeedbackFormPurpose;
use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Enums\FeedbackFormVisibility;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\FilamentFeedback\Resources\FeedbackFormResource;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;

final class CreateFeedbackForm extends CreateRecord
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
                    ->options(FeedbackFormStatus::options())
                    ->default('draft'),
                Select::make('visibility')
                    ->options(FeedbackFormVisibility::options())
                    ->default('private'),
                Toggle::make('is_anonymous_allowed')
                    ->default(true),
                Toggle::make('is_anonymity_optional'),
                Toggle::make('is_login_required'),
                Toggle::make('is_one_response_per_respondent'),
                Toggle::make('is_edit_after_submit_allowed'),
                DateTimePicker::make('opens_at'),
                DateTimePicker::make('closes_at'),
            ]);
    }

    protected function handleRecordCreation(array $data): FeedbackForm
    {
        return app(CreateFeedbackFormAction::class)->execute(
            new CreateFeedbackFormData(
                name: $data['name'],
                slug: $data['slug'] ?? null,
                purpose: $data['purpose'] ?? 'general',
                status: $data['status'] ?? 'draft',
                visibility: $data['visibility'] ?? 'private',
                isAnonymousAllowed: (bool) ($data['is_anonymous_allowed'] ?? true),
                isAnonymityOptional: (bool) ($data['is_anonymity_optional'] ?? false),
                isLoginRequired: (bool) ($data['is_login_required'] ?? false),
                isOneResponsePerRespondent: (bool) ($data['is_one_response_per_respondent'] ?? false),
                isEditAfterSubmitAllowed: (bool) ($data['is_edit_after_submit_allowed'] ?? false),
                opensAt: $data['opens_at'] ?? null,
                closesAt: $data['closes_at'] ?? null,
            ),
        );
    }

    protected function afterCreate(): void
    {
        $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
    }
}
