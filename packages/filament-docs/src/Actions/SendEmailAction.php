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

final class SendEmailAction
{
    /**
     * Create the send email action.
     */
    public static function make(string $name = 'send_email'): Action
    {
        return Action::make($name)
            ->label(__('Send Email'))
            ->icon('heroicon-o-envelope')
            ->color('info')
            ->modalHeading(__('Send Document via Email'))
            ->modalDescription(__('Send this document to the specified recipient.'))
            ->form([
                TextInput::make('to')
                    ->label(__('Recipient Email'))
                    ->email()
                    ->required()
                    ->default(fn (Doc $record): ?string => self::getRecipientEmail($record)),

                TextInput::make('cc')
                    ->label(__('CC'))
                    ->email()
                    ->nullable(),

                Select::make('template_id')
                    ->label(__('Email Template'))
                    ->options(function (): array {
                        $templateClass = config('docs.models.email_template');

                        if (! $templateClass || ! class_exists($templateClass)) {
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
                    ->default(fn (Doc $record): string => __('Document: :number', ['number' => $record->doc_number])),

                Textarea::make('message')
                    ->label(__('Message'))
                    ->rows(5)
                    ->default(__('Please find the attached document.')),
            ])
            ->action(function (Doc $record, array $data): void {
                self::sendEmail($record, $data);
            })
            ->visible(fn (Doc $record): bool => self::getRecipientEmail($record) !== null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function sendEmail(Doc $record, array $data): void
    {
        $emailService = app(DocEmailService::class);

        try {
            $emailService->send(
                doc: $record,
                recipientEmail: $data['to'],
                recipientName: self::getRecipientName($record),
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

    private static function getRecipientEmail(Doc $doc): ?string
    {
        $customerData = $doc->customer_data;

        return is_array($customerData) ? ($customerData['email'] ?? null) : null;
    }

    private static function getRecipientName(Doc $doc): ?string
    {
        $customerData = $doc->customer_data;

        return is_array($customerData) ? ($customerData['name'] ?? null) : null;
    }
}
