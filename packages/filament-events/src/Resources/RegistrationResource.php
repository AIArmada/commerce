<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Enums\RegistrationAttendanceSource;
use AIArmada\Events\Enums\RegistrationStatus;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use AIArmada\Events\Services\RegistrationService;
use AIArmada\FilamentEvents\Resources\RegistrationResource\Pages;
use AIArmada\FilamentEvents\Resources\RegistrationResource\Schemas\RegistrationForm;
use AIArmada\FilamentEvents\Resources\RegistrationResource\Schemas\RegistrationInfolist;
use AIArmada\FilamentEvents\Resources\RegistrationResource\Tables\RegistrationTable;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final class RegistrationResource extends Resource
{
    protected static ?string $model = Registration::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $recordTitleAttribute = 'code';

    public static function getNavigationGroup(): ?string
    {
        return (string) config('filament-events.navigation.group', 'Events');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-events.navigation.resources.registrations', 5);
    }

    /**
     * @return Builder<Registration>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Registration> $query */
        $query = parent::getEloquentQuery();

        return OwnerUiScope::apply($query, includeGlobal: false)
            ->with(['occurrence.event', 'order', 'orderItem', 'purchaserCustomer', 'participantCustomer']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', RegistrationStatus::Confirmed)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return RegistrationForm::configure($schema);
    }

    /**
     * @return array<int, mixed>
     */
    public static function formSchema(bool $includeOccurrenceField = true): array
    {
        return RegistrationForm::formSchema(includeOccurrenceField: $includeOccurrenceField);
    }

    public static function table(Table $table): Table
    {
        return RegistrationTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RegistrationInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistrations::route('/'),
            'create' => Pages\CreateRegistration::route('/create'),
            'view' => Pages\ViewRegistration::route('/{record}'),
            'edit' => Pages\EditRegistration::route('/{record}/edit'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeCreateData(array $data, ?Occurrence $occurrence = null): array
    {
        $resolvedOccurrence = $occurrence;

        if (! $resolvedOccurrence instanceof Occurrence) {
            $resolvedOccurrence = static::resolveCreateOccurrence($data['occurrence_id'] ?? null);
        }

        if (! $resolvedOccurrence instanceof Occurrence) {
            return $data;
        }

        if ($resolvedOccurrence->requiresApproval()) {
            $data['status'] = RegistrationStatus::Pending->value;
            $data['checked_in_at'] = null;
            $data['cancelled_at'] = null;
        }

        return $data;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'first_name', 'last_name', 'email', 'phone', 'company'];
    }

    public static function checkInAction(): Action
    {
        return Action::make('check_in')
            ->label('Check In')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Registration $record): bool => $record->status->canCheckIn())
            ->action(fn (Registration $record): Registration => app(RegistrationService::class)->checkIn($record, [
                'source' => 'filament',
            ]));
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Registration $record): bool => in_array($record->status, [RegistrationStatus::Pending, RegistrationStatus::Waitlisted], true))
            ->action(function (Registration $record): Registration {
                $user = auth()->user();

                return app(RegistrationService::class)->approve(
                    $record,
                    $user instanceof Model ? $user : null,
                    [
                        'source' => 'filament',
                    ],
                );
            });
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->schema([
                Textarea::make('reason')
                    ->rows(3)
                    ->maxLength(500),
            ])
            ->visible(fn (Registration $record): bool => in_array($record->status, [RegistrationStatus::Pending, RegistrationStatus::Waitlisted], true))
            ->action(function (Registration $record, array $data): Registration {
                $user = auth()->user();

                return app(RegistrationService::class)->reject(
                    $record,
                    $user instanceof Model ? $user : null,
                    is_string($data['reason'] ?? null) ? $data['reason'] : null,
                    [
                        'source' => 'filament',
                    ],
                );
            });
    }

    public static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->schema([
                Textarea::make('reason')
                    ->rows(3)
                    ->maxLength(500),
            ])
            ->visible(fn (Registration $record): bool => $record->status !== RegistrationStatus::Cancelled)
            ->action(fn (Registration $record, array $data): Registration => app(RegistrationService::class)->cancel(
                $record,
                is_string($data['reason'] ?? null) ? $data['reason'] : null,
            ));
    }

    public static function registrationContactLabel(Registration $registration): string
    {
        if (is_string($registration->email) && mb_trim($registration->email) !== '') {
            return $registration->email;
        }

        return $registration->attendance_source instanceof RegistrationAttendanceSource
            ? $registration->attendance_source->label()
            : RegistrationAttendanceSource::Registration->label();
    }

    private static function resolveCreateOccurrence(mixed $occurrenceId): ?Occurrence
    {
        if ($occurrenceId === null) {
            return null;
        }

        if (! is_string($occurrenceId) && ! is_int($occurrenceId)) {
            throw ValidationException::withMessages([
                'occurrence_id' => 'The selected occurrence is invalid.',
            ]);
        }

        try {
            $occurrence = OwnerWriteGuard::findOrFailForOwner(
                Occurrence::class,
                (string) $occurrenceId,
                includeGlobal: false,
                message: 'The selected occurrence is not accessible in the current owner scope.',
            );
        } catch (AuthorizationException | InvalidArgumentException | RuntimeException) {
            throw ValidationException::withMessages([
                'occurrence_id' => 'The selected occurrence is not accessible in the current owner scope.',
            ]);
        }

        if (! $occurrence instanceof Occurrence) {
            throw ValidationException::withMessages([
                'occurrence_id' => 'The selected occurrence is invalid.',
            ]);
        }

        return $occurrence;
    }
}
