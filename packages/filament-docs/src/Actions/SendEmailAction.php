<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Actions;

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocEmailTemplate;
use AIArmada\Docs\Services\DocEmailService;
use AIArmada\FilamentDocs\Support\DocsOwnerScope;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

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
                    ->options(function (?Doc $record = null): array {
                        /** @var Builder<DocEmailTemplate> $query */
                        $query = DocEmailTemplate::query();

                        /** @var Builder<DocEmailTemplate> $query */
                        $query = DocsOwnerScope::apply($query);

                        $query
                            ->where('is_active', true)
                            ->orderBy('name');

                        if ($record !== null) {
                            $query->where('doc_type', $record->doc_type);
                        }

                        return $query->pluck('name', 'id')->toArray();
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
        try {
            DocsOwnerScope::assertCanMutateDoc($record);

            $emailService = app(DocEmailService::class);

            $template = null;
            if (! empty($data['template_id'])) {
                /** @var Builder<DocEmailTemplate> $query */
                $query = DocEmailTemplate::query();

                /** @var Builder<DocEmailTemplate> $query */
                $query = DocsOwnerScope::apply($query);

                $template = $query
                    ->where('is_active', true)
                    ->where('doc_type', $record->doc_type)
                    ->find($data['template_id']);

                if ($template === null) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'template_id' => __('Invalid email template selection.'),
                    ]);
                }
            }

            $emailService->send(
                doc: $record,
                recipientEmail: $data['to'],
                recipientName: self::getRecipientName($record),
                template: $template,
            );

            Notification::make()
                ->title(__('Email Sent'))
                ->body(__('The document has been sent to :email', ['email' => $data['to']]))
                ->success()
                ->send();
        } catch (Throwable $e) {
            Log::warning('Filament docs send email action failed.', [
                'doc_id' => $record->getKey(),
                'recipient' => $data['to'] ?? null,
                'template_id' => $data['template_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

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
