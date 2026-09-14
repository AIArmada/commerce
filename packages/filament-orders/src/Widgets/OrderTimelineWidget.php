<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Widgets;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\Orders\Models\Order;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * @property-read Schema $form
 */
final class OrderTimelineWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null && Gate::forUser($user)->allows('viewAny', Order::class);
    }

    public ?Order $record = null;

    public ?array $noteData = [];

    /** @var view-string */
    protected string $view = 'filament-orders::widgets.order-timeline';

    protected int | string | array $columnSpan = 'full';

    public function mount(Order $record): void
    {
        $this->record = $record;
    }

    public function getTimelineEvents(): Collection
    {
        if (! $this->record) {
            return collect([]);
        }

        $this->record->loadMissing([
            'customer',
            'payments',
            'orderNotes.user',
        ]);

        $events = collect([]);

        // Order created event
        $customerName = $this->record->customer?->getAttribute('full_name')
            ?? $this->record->customer?->getAttribute('name')
            ?? 'Guest';

        $events->push([
            'type' => 'created',
            'title' => 'Order Created',
            'description' => 'Order was placed by ' . $customerName,
            'icon' => 'heroicon-o-shopping-cart',
            'color' => 'success',
            'timestamp' => $this->record->created_at,
        ]);

        // Payment events
        foreach ($this->record->payments ?? [] as $payment) {
            $currency = $this->record->currency ?? (string) config('orders.currency.default', 'MYR');
            $statusLabel = $payment->status->label();

            $events->push([
                'type' => 'payment',
                'title' => 'Payment ' . $statusLabel,
                'description' => sprintf(
                    '%s payment of %s via %s',
                    $statusLabel,
                    MoneyFormatter::formatMinor($payment->amount, $currency),
                    $payment->gateway
                ),
                'icon' => $payment->status->isFinal() ? 'heroicon-o-check-circle' : 'heroicon-o-credit-card',
                'color' => $payment->status->color(),
                'timestamp' => $payment->created_at,
            ]);
        }

        // Shipment events
        if ($this->record->shipped_at) {
            $shippingData = $this->record->metadata['shipping'] ?? [];
            $carrier = is_array($shippingData) ? ($shippingData['carrier'] ?? 'Unknown') : 'Unknown';
            $trackingNumber = is_array($shippingData) ? ($shippingData['tracking_number'] ?? 'N/A') : 'N/A';

            $events->push([
                'type' => 'shipped',
                'title' => 'Order Shipped',
                'description' => sprintf(
                    'Shipped via %s (Tracking: %s)',
                    $carrier,
                    $trackingNumber
                ),
                'icon' => 'heroicon-o-truck',
                'color' => 'info',
                'timestamp' => $this->record->shipped_at,
            ]);
        }

        // Notes
        foreach ($this->record->orderNotes as $note) {
            $causerName = $note->user?->getAttribute('name')
                ?? $note->user?->getAttribute('full_name')
                ?? 'System';

            $events->push([
                'type' => 'note',
                'title' => 'Note Added',
                'description' => $note->content,
                'icon' => 'heroicon-o-chat-bubble-left-ellipsis',
                'color' => 'gray',
                'timestamp' => $note->created_at,
                'causer' => $causerName,
            ]);
        }

        return $events->sortByDesc('timestamp')->values();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Textarea::make('content')
                    ->label('Note')
                    ->required()
                    ->maxLength(2000)
                    ->rows(2)
                    ->placeholder('Add a note to this order timeline...'),

                Forms\Components\Select::make('visibility')
                    ->label('Visibility')
                    ->options([
                        'internal' => 'Internal Only',
                        'customer' => 'Customer Visible',
                    ])
                    ->in(['internal', 'customer'])
                    ->default('internal')
                    ->helperText('Customer will see customer-visible notes in their order history'),
            ])
            ->statePath('noteData');
    }

    public function addNote(): void
    {
        $data = $this->form->getState();

        if (! $this->record) {
            return;
        }

        $user = Filament::auth()->user();

        if (! $user || ! Gate::forUser($user)->allows('addNote', $this->record)) {
            Notification::make()
                ->title('Not authorized')
                ->danger()
                ->send();

            return;
        }

        $note = self::normalizeNoteInput($data);

        if ($note === null) {
            Notification::make()
                ->title('Note must be 1–2000 characters with a valid visibility')
                ->danger()
                ->send();

            return;
        }

        try {
            $this->record->orderNotes()->create([
                'content' => $note['content'],
                'visibility' => $note['visibility'],
                'user_id' => Filament::auth()->id(),
            ]);
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->title('Failed to add note')
                ->body('Please try again. If the problem persists, contact support.')
                ->danger()
                ->send();

            return;
        }

        $this->noteData = [];
        $this->form->fill();

        $this->dispatch('note-added');

        Notification::make()
            ->title('Note added successfully')
            ->success()
            ->send();
    }

    /**
     * Server-side note guard: form rules are client-bypassable over Livewire,
     * so tampered payloads are re-validated here. Returns null when invalid.
     *
     * @param  array<string, mixed>  $data
     * @return array{content: string, visibility: string}|null
     */
    public static function normalizeNoteInput(array $data): ?array
    {
        $content = $data['content'] ?? null;
        $visibility = $data['visibility'] ?? 'internal';

        if (! is_string($content) || mb_trim($content) === '' || mb_strlen($content) > 2000) {
            return null;
        }

        if (! in_array($visibility, ['internal', 'customer'], true)) {
            return null;
        }

        return ['content' => $content, 'visibility' => $visibility];
    }
}
