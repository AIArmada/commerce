<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages\Portal;

use AIArmada\Affiliates\Models\AffiliateSupportMessage;
use AIArmada\Affiliates\Models\AffiliateSupportTicket;
use AIArmada\Affiliates\Models\AffiliateTaxDocument;
use AIArmada\FilamentAffiliates\Concerns\PortalPage;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class PortalSupport extends PortalPage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?int $navigationSort = 5;

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

        $ticket = AffiliateSupportTicket::create([
            'affiliate_id' => $affiliate->getKey(),
            'subject' => $validated['subject'],
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'status' => 'open',
        ]);

        AffiliateSupportMessage::create([
            'ticket_id' => $ticket->getKey(),
            'affiliate_id' => $affiliate->getKey(),
            'message' => $validated['message'],
            'is_staff_reply' => false,
        ]);

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

        $ticket = AffiliateSupportTicket::query()
            ->where('affiliate_id', $affiliate->getKey())
            ->whereKey($ticketId)
            ->first();

        if (! $ticket) {
            Notification::make()
                ->title(__('Ticket not found'))
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

        AffiliateSupportMessage::create([
            'ticket_id' => $ticket->getKey(),
            'affiliate_id' => $affiliate->getKey(),
            'message' => $message,
            'is_staff_reply' => false,
        ]);

        $ticket->update([
            'status' => 'open',
        ]);

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

        $tickets = AffiliateSupportTicket::query()
            ->where('affiliate_id', $affiliate->getKey())
            ->with(['messages'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (AffiliateSupportTicket $ticket): array {
                return [
                    'id' => (string) $ticket->getKey(),
                    'subject' => (string) $ticket->subject,
                    'category' => (string) $ticket->category,
                    'priority' => (string) $ticket->priority,
                    'status' => (string) $ticket->status,
                    'messages' => $ticket->messages->map(fn (AffiliateSupportMessage $message): array => [
                        'id' => (string) $message->getKey(),
                        'message' => (string) $message->message,
                        'is_staff_reply' => (bool) $message->is_staff_reply,
                        'created_at' => $message->created_at?->toDateTimeString(),
                    ])->all(),
                    'reply_message' => (string) ($this->replyMessages[$ticket->getKey()] ?? ''),
                ];
            })
            ->values()
            ->all();

        $taxDocuments = AffiliateTaxDocument::query()
            ->where('affiliate_id', $affiliate->getKey())
            ->orderByDesc('generated_at')
            ->get()
            ->map(fn (AffiliateTaxDocument $document): array => [
                'id' => (string) $document->getKey(),
                'document_type' => (string) $document->document_type,
                'tax_year' => (int) $document->tax_year,
                'status' => (string) $document->status,
                'total_amount_minor' => (int) $document->total_amount_minor,
                'currency' => (string) $document->currency,
                'document_path' => $document->document_path,
                'notes' => $document->notes,
                'generated_at' => $document->generated_at?->toDateTimeString(),
                'sent_at' => $document->sent_at?->toDateTimeString(),
            ])->all();

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
