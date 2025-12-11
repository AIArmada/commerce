<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Widgets;

use AIArmada\Orders\Models\Order;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class OrderTimelineWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    public ?Order $record = null;

    public ?array $noteData = [];

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

        $events = collect([]);

        // Order created event
        $events->push([
            'type' => 'created',
            'title' => 'Order Created',
            'description' => 'Order was placed by ' . $this->record->customer->full_name,
            'icon' => 'heroicon-o-shopping-cart',
            'color' => 'success',
            'timestamp' => $this->record->created_at,
        ]);

        // Status transitions from activity log (if using spatie/laravel-activitylog)
        if (method_exists($this->record, 'activities')) {
            foreach ($this->record->activities as $activity) {
                if ($activity->description === 'status_changed') {
                    $events->push([
                        'type' => 'status_change',
                        'title' => 'Status Updated',
                        'description' => sprintf(
                            'Status changed from %s to %s',
                            $activity->properties['old_status'] ?? 'Unknown',
                            $activity->properties['new_status'] ?? 'Unknown'
                        ),
                        'icon' => 'heroicon-o-arrow-path',
                        'color' => 'info',
                        'timestamp' => $activity->created_at,
                        'causer' => $activity->causer?->name ?? 'System',
                    ]);
                }
            }
        }

        // Payment events
        foreach ($this->record->payments ?? [] as $payment) {
            $events->push([
                'type' => 'payment',
                'title' => 'Payment ' . ucfirst($payment->status),
                'description' => sprintf(
                    '%s payment of %s via %s',
                    ucfirst($payment->status),
                    'RM ' . number_format($payment->amount / 100, 2),
                    $payment->method
                ),
                'icon' => $payment->status === 'completed' ? 'heroicon-o-check-circle' : 'heroicon-o-credit-card',
                'color' => $payment->status === 'completed' ? 'success' : 'warning',
                'timestamp' => $payment->created_at,
            ]);
        }

        // Shipment events
        if ($this->record->shipped_at) {
            $events->push([
                'type' => 'shipped',
                'title' => 'Order Shipped',
                'description' => sprintf(
                    'Shipped via %s (Tracking: %s)',
                    $this->record->shipping_carrier ?? 'Unknown',
                    $this->record->tracking_number ?? 'N/A'
                ),
                'icon' => 'heroicon-o-truck',
                'color' => 'info',
                'timestamp' => $this->record->shipped_at,
            ]);
        }

        // Notes
        foreach ($this->record->orderNotes as $note) {
            $events->push([
                'type' => 'note',
                'title' => 'Note Added',
                'description' => $note->content,
                'icon' => 'heroicon-o-chat-bubble-left-ellipsis',
                'color' => 'gray',
                'timestamp' => $note->created_at,
                'causer' => $note->user?->name ?? 'System',
            ]);
        }

        return $events->sortByDesc('timestamp')->values();
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('content')
                    ->label('Note')
                    ->required()
                    ->rows(2)
                    ->placeholder('Add a note to this order timeline...'),

                Forms\Components\Toggle::make('is_visible_to_customer')
                    ->label('Visible to Customer')
                    ->default(false)
                    ->helperText('Customer will see this note in their order history'),
            ])
            ->statePath('noteData');
    }

    public function addNote(): void
    {
        $data = $this->form->getState();

        $this->record->orderNotes()->create([
            'content' => $data['content'],
            'is_visible_to_customer' => $data['is_visible_to_customer'] ?? false,
            'user_id' => auth()->id(),
        ]);

        $this->noteData = [];
        $this->form->fill();

        $this->dispatch('note-added');

        \Filament\Notifications\Notification::make()
            ->title('Note added successfully')
            ->success()
            ->send();
    }
}
