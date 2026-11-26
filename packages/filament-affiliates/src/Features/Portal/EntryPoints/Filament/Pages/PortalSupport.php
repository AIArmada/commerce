<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Pages;

use AIArmada\Affiliates\Features\Support\Api\SupportTicketApi;
use AIArmada\Affiliates\Features\Tax\Api\TaxDocumentApi;
use AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Concerns\PortalPage;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class PortalSupport extends PortalPage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.portal.support';

    public string $subject = '';

    public string $category = 'general';

    public string $priority = 'medium';

    public string $message = '';

    /**
     * @var array<string, string>
     */
    public array $replyMessages = [];

    public static function getNavigationLabel(): string
    {
        return __('Support');
    }

    public function getTitle(): string | Htmlable
    {
        return __('Support & Compliance');
    }

    public function createTicket(): void
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            Notification::make()
                ->title(__('No Affiliate Account'))
                ->body(__('You do not have an affiliate account yet.'))
                ->danger()
                ->send();

            return;
        }

        $validated = validator([
            'subject' => $this->subject,
            'category' => $this->category,
            'priority' => $this->priority,
            'message' => $this->message,
        ], [
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:general,billing,technical,tax,account'],
            'priority' => ['required', 'string', 'in:low,medium,high,urgent'],
            'message' => ['required', 'string', 'max:5000'],
        ])->validate();

        app(SupportTicketApi::class)->open(
            (string) $affiliate->getKey(),
            $validated['subject'],
            $validated['category'],
            $validated['priority'],
            $validated['message'],
        );

        $this->subject = '';
        $this->category = 'general';
        $this->priority = 'medium';
        $this->message = '';

        Notification::make()
            ->title(__('Ticket created'))
            ->body(__('Your support request has been submitted.'))
            ->success()
            ->send();
    }

    public function replyToTicket(string $ticketId): void
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            Notification::make()
                ->title(__('No Affiliate Account'))
                ->body(__('You do not have an affiliate account yet.'))
                ->danger()
                ->send();

            return;
        }

        $validated = validator([
            'message' => (string) ($this->replyMessages[$ticketId] ?? ''),
        ], [
            'message' => ['required', 'string', 'max:5000'],
        ])->validate();

        $message = mb_trim($validated['message']);

        if ($message === '') {
            Notification::make()
                ->title(__('Reply cannot be empty'))
                ->danger()
                ->send();

            return;
        }

        if (! app(SupportTicketApi::class)->replyAsAffiliate((string) $affiliate->getKey(), $ticketId, $message)) {
            Notification::make()->title(__('Ticket not found'))->danger()->send();

            return;
        }

        $this->replyMessages[$ticketId] = '';

        Notification::make()
            ->title(__('Reply sent'))
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return [
                'hasAffiliate' => false,
                'tickets' => [],
                'taxDocuments' => [],
            ];
        }

        $tickets = app(SupportTicketApi::class)->ticketsFor((string) $affiliate->getKey());

        foreach ($tickets as &$ticket) {
            $ticket['reply_message'] = (string) ($this->replyMessages[$ticket['id']] ?? '');
        }
        unset($ticket);

        $taxDocuments = array_map(static fn ($document): array => [
            'id' => $document->id,
            'document_type' => $document->documentType,
            'tax_year' => $document->year,
            'status' => $document->status->value,
            'total_amount_minor' => $document->amountMinor,
            'currency' => $document->currency,
            'notes' => $document->notes,
            'generated_at' => $document->generatedAt,
            'sent_at' => $document->sentAt,
        ], app(TaxDocumentApi::class)->documentsFor((string) $affiliate->getKey(), 50));

        return [
            'hasAffiliate' => true,
            'tickets' => $tickets,
            'taxDocuments' => $taxDocuments,
            'ticketCategories' => [
                'general' => __('General'),
                'billing' => __('Billing'),
                'technical' => __('Technical'),
                'tax' => __('Tax'),
                'account' => __('Account'),
            ],
            'ticketPriorities' => [
                'low' => __('Low'),
                'medium' => __('Medium'),
                'high' => __('High'),
                'urgent' => __('Urgent'),
            ],
            'documentStatuses' => [
                'pending_info' => __('Pending Info'),
                'generated' => __('Generated'),
                'sent' => __('Sent'),
            ],
        ];
    }
}
