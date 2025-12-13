<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Actions;

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocEmailService;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class SendEmailAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->name('send_email');

        $this->label(__('Send Email'));

        $this->icon('heroicon-o-envelope');

        $this->color('info');

        $this->modalHeading(__('Send Document via Email'));

        $this->modalDescription(__('Send this document to the specified recipient.'));

        $this->form($this->getEmailForm());

        $this->action(function (Doc $record, array $data): void {
            $this->sendEmail($record, $data);
        });

        $this->visible(function (Doc $record): bool {
            return $record->recipient_email !== null;
        });
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'send_email');
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function getEmailForm(): array
    {
        return [
            TextInput::make('to')
                ->label(__('Recipient Email'))
                ->email()
                ->required()
                ->default(fn (Doc $record): ?string => $record->recipient_email),

            TextInput::make('cc')
                ->label(__('CC'))
                ->email()
                ->nullable(),

            Select::make('template_id')
                ->label(__('Email Template'))
                ->options(function (): array {
                    $templateClass = config('docs.models.email_template');

                    if (! class_exists($templateClass)) {
                        return [];
                    }

                    return $templateClass::query()
                        ->where('is_active', true)
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->nullable()
                ->searchable(),

            TextInput::make('subject')
                ->label(__('Subject'))
                ->required()
                ->default(fn (Doc $record): string => __('Document: :number', ['number' => $record->document_number])),

            Textarea::make('message')
                ->label(__('Message'))
                ->rows(5)
                ->default(__('Please find the attached document.')),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function sendEmail(Doc $record, array $data): void
    {
        $emailService = app(DocEmailService::class);

        try {
            $emailService->send(
                doc: $record,
                recipientEmail: $data['to'],
                recipientName: $record->recipient_name,
            );

            Notification::make()
                ->title(__('Email Sent'))
                ->body(__('The document has been sent to :email', ['email' => $data['to']]))
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title(__('Email Failed'))
                ->body(__('Failed to send the document. Please try again.'))
                ->danger()
                ->send();
        }
    }
}
