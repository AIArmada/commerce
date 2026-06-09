<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\RegistrationResource\Schemas;

use AIArmada\Events\Enums\RegistrationAttendanceSource;
use AIArmada\Events\Enums\RegistrationStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class RegistrationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Registration')
                    ->schema([
                        TextEntry::make('code')
                            ->copyable(),
                        TextEntry::make('full_name')
                            ->label('Participant'),
                        TextEntry::make('email')
                            ->copyable()
                            ->placeholder('Not set'),
                        TextEntry::make('phone')
                            ->copyable()
                            ->placeholder('Not set'),
                        TextEntry::make('company')
                            ->placeholder('Not set'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (RegistrationStatus $state): string => $state->label())
                            ->color(fn (RegistrationStatus $state): string => $state->color()),
                        TextEntry::make('attendance_source')
                            ->label('Attendance Source')
                            ->badge()
                            ->formatStateUsing(fn (RegistrationAttendanceSource $state): string => $state->label())
                            ->color(fn (RegistrationAttendanceSource $state): string => $state === RegistrationAttendanceSource::WalkIn ? 'info' : 'primary'),
                        TextEntry::make('occurrence.approval_required')
                            ->label('Approval Required')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Required' : 'Open')
                            ->color(fn (bool $state): string => $state ? 'warning' : 'success'),
                        TextEntry::make('occurrence.waitlist_enabled')
                            ->label('Waitlist')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Enabled' : 'Disabled')
                            ->color(fn (bool $state): string => $state ? 'info' : 'gray'),
                        TextEntry::make('occurrence.event.name')
                            ->label('Event'),
                        TextEntry::make('occurrence.starts_at')
                            ->label('Starts')
                            ->dateTime('d M Y H:i'),
                        TextEntry::make('checked_in_at')
                            ->dateTime()
                            ->placeholder('Not checked in'),
                        TextEntry::make('cancelled_at')
                            ->dateTime()
                            ->placeholder('Not cancelled'),
                    ])
                    ->columns(3),

                Section::make('Commerce Links')
                    ->schema([
                        TextEntry::make('order.order_number')
                            ->label('Order')
                            ->copyable()
                            ->placeholder('Not linked'),
                        TextEntry::make('orderItem.name')
                            ->label('Order Item')
                            ->placeholder('Not linked'),
                        TextEntry::make('purchaserCustomer.full_name')
                            ->label('Purchaser')
                            ->placeholder('Not linked'),
                        TextEntry::make('participantCustomer.full_name')
                            ->label('Participant Customer')
                            ->placeholder('Not linked'),
                    ])
                    ->columns(4),
            ]);
    }
}
