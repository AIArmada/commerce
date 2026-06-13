<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Pages;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventNotificationBatch;
use AIArmada\Events\Models\EventNotificationDelivery;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

final class NotificationCenter extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-bell';

    protected static string | UnitEnum | null $navigationGroup = 'Events';

    protected static ?string $title = 'Notification Center';

    protected static ?string $slug = 'events/notifications';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                EventNotificationBatch::query()
                    ->whereHas('event', fn (Builder $eventQuery): Builder => OwnerUiScope::apply($eventQuery, includeGlobal: false))
                    ->with('event')
            )
            ->columns([
                TextColumn::make('event.title')->label('Event')->searchable(),
                TextColumn::make('title')->label('Subject')->searchable(),
                TextColumn::make('audience_scope')->badge(),
                TextColumn::make('status')->badge()->colors([
                    'warning' => 'pending',
                    'success' => 'sent',
                    'danger' => 'failed',
                    'gray' => 'cancelled',
                ]),
                TextColumn::make('scheduled_at')->dateTime()->sortable(),
                TextColumn::make('sent_at')->dateTime()->sortable()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['pending' => 'Pending', 'sent' => 'Sent', 'failed' => 'Failed', 'cancelled' => 'Cancelled']),
            ])
            ->actions([
                Action::make('send')
                    ->label('Send Now')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->action(function (EventNotificationBatch $record): void {
                        OwnerWriteGuard::findOrFailForOwner(Event::class, $record->event_id);

                        $record->update(['status' => 'sent', 'sent_at' => CarbonImmutable::now()]);
                    })
                    ->visible(fn (EventNotificationBatch $record) => $record->status === 'pending'),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (EventNotificationBatch $record): void {
                        OwnerWriteGuard::findOrFailForOwner(Event::class, $record->event_id);

                        $record->update(['status' => 'cancelled', 'cancelled_at' => CarbonImmutable::now()]);
                    })
                    ->visible(fn (EventNotificationBatch $record) => $record->status === 'pending'),
                Action::make('viewDeliveries')
                    ->label('Deliveries')
                    ->icon('heroicon-o-list-bullet')
                    ->modalHeading('Delivery Details')
                    ->modalContent(function (EventNotificationBatch $record) {
                        $deliveries = EventNotificationDelivery::query()
                            ->where('event_notification_batch_id', $record->id)
                            ->get();

                        if ($deliveries->isEmpty()) {
                            return new HtmlString('<p class="text-sm text-gray-500">No delivery records yet.</p>');
                        }

                        $html = '<table class="w-full text-left text-sm"><thead><tr><th class="pr-4">Recipient</th><th class="pr-4">Channel</th><th class="pr-4">Status</th><th>Sent At</th></tr></thead><tbody>';
                        foreach ($deliveries as $d) {
                            $html .= sprintf(
                                '<tr><td class="pr-4">%s #%s</td><td class="pr-4">%s</td><td class="pr-4">%s</td><td>%s</td></tr>',
                                e((string) $d->recipient_type),
                                e((string) $d->recipient_id),
                                e((string) ($d->channel ?? '')),
                                e((string) $d->status),
                                e($d->sent_at?->format('Y-m-d H:i') ?? '—'),
                            );
                        }
                        $html .= '</tbody></table>';

                        return new HtmlString($html);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createBatch')
                ->label('New Notification')
                ->icon('heroicon-o-plus')
                ->form([
                    Select::make('event_id')
                        ->label('Event')
                        ->relationship('event', 'title', modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false))
                        ->searchable()
                        ->required(),
                    TextInput::make('title')->label('Subject')->required(),
                    Select::make('audience_scope')
                        ->options(['registrants' => 'Registrants', 'followers' => 'Followers', 'all' => 'All'])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    OwnerWriteGuard::findOrFailForOwner(Event::class, $data['event_id']);

                    EventNotificationBatch::query()->create([
                        'event_id' => $data['event_id'],
                        'title' => $data['title'],
                        'audience_scope' => $data['audience_scope'],
                        'status' => 'pending',
                    ]);
                }),
        ];
    }

    public function getView(): string
    {
        return 'filament-pages::simple-page';
    }
}
