<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrganizations\RelationManagers;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\Membership\Enums\InvitationStatus;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Membership\Support\InvitationChannelManager;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class AbstractMemberInvitationsRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'invitations';

    protected static ?string $title = 'Member Invitations';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->modifyInvitationsQuery($query))
            ->columns($this->getInvitationColumns())
            ->headerActions([
                Action::make('inviteMember')
                    ->label('Invite member')
                    ->form([
                        Select::make('channel')
                            ->label('Channel')
                            ->options(fn (): array => $this->channelOptions())
                            ->default($this->defaultInviteChannel())
                            ->required(),
                        TextInput::make('recipient')
                            ->label('Recipient')
                            ->required()
                            ->maxLength(255),
                        Select::make('role')
                            ->label('Role')
                            ->options(fn (): array => $this->roleOptions())
                            ->default($this->defaultInviteRole())
                            ->required(),
                        DateTimePicker::make('expires_at')
                            ->label('Expires at')
                            ->seconds(false),
                    ])
                    ->action(function (array $data): void {
                        $actor = Filament::auth()->user();
                        abort_unless($actor instanceof Model, 403);

                        $owner = $this->getOwnerRecord();
                        $this->authorizeInvitationMutation('invite', $owner);

                        $this->createInvitation(
                            $owner,
                            (string) $data['recipient'],
                            (string) ($data['role'] ?? $this->defaultInviteRole()),
                            $actor,
                            $this->normalizeExpiresAt($data['expires_at'] ?? null),
                            (string) ($data['channel'] ?? $this->defaultInviteChannel()),
                        );
                    }),
            ])
            ->actions([
                Action::make('revokeInvitation')
                    ->label('Revoke')
                    ->color('danger')
                    ->hidden(fn (MembershipInvitation $record): bool => $record->isAccepted()
                        || $record->isRevoked()
                        || $record->status === InvitationStatus::Expired)
                    ->requiresConfirmation()
                    ->action(function (MembershipInvitation $record): void {
                        $actor = Filament::auth()->user();
                        abort_unless($actor instanceof Model, 403);

                        $owner = $this->getOwnerRecord();
                        $this->authorizeInvitationMutation('revoke', $owner);

                        $this->revokeInvitation($owner, $record, $actor);
                    }),
            ]);
    }

    abstract protected function createInvitation(
        Model $owner,
        string $recipient,
        string $role,
        Model $actor,
        ?CarbonInterface $expiresAt,
        string $channel,
    ): void;

    abstract protected function revokeInvitation(
        Model $owner,
        MembershipInvitation $invitation,
        Model $actor,
    ): void;

    protected function authorizeInvitationMutation(string $ability, Model $owner): void
    {
        abort(403);
    }

    /**
     * @param  Builder<MembershipInvitation>  $query
     * @return Builder<MembershipInvitation>
     */
    protected function modifyInvitationsQuery(Builder $query): Builder
    {
        return $query->with(['inviter', 'acceptor', 'revoker'])->latest('created_at');
    }

    /**
     * @return array<int, TextColumn>
     */
    protected function getInvitationColumns(): array
    {
        return [
            TextColumn::make('channel')
                ->label('Channel')
                ->badge()
                ->formatStateUsing(fn (string $state): string => $this->channelLabel($state)),
            TextColumn::make('recipient')
                ->searchable()
                ->sortable(),
            TextColumn::make('role')
                ->label('Role')
                ->badge()
                ->formatStateUsing(static fn (string $state): string => MemberRole::tryFrom($state)?->label() ?? $state),
            TextColumn::make('inviter.name')
                ->label('Invited by')
                ->default('—'),
            TextColumn::make('expires_at')
                ->label('Expires')
                ->dateTime('d M Y h:i A')
                ->placeholder('No expiry')
                ->sortable(),
            TextColumn::make('status')
                ->badge()
                ->getStateUsing(fn (MembershipInvitation $record): string => $this->invitationStatusLabel($record))
                ->color(fn (string $state): string => $this->invitationStatusColor($state)),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function roleOptions(): array
    {
        return collect(MemberRole::cases())
            ->mapWithKeys(static fn (MemberRole $role): array => [$role->value => $role->label()])
            ->all();
    }

    protected function defaultInviteRole(): string
    {
        return MemberRole::Viewer->value;
    }

    /**
     * @return array<string, string>
     */
    protected function channelOptions(): array
    {
        return app(InvitationChannelManager::class)->options();
    }

    protected function defaultInviteChannel(): string
    {
        return app(InvitationChannelManager::class)->defaultChannelName();
    }

    protected function channelLabel(string $channel): string
    {
        $manager = app(InvitationChannelManager::class);

        if (! $manager->hasChannel($channel)) {
            return $channel;
        }

        return $manager->channel($channel)->label();
    }

    protected function invitationStatusLabel(MembershipInvitation $invitation): string
    {
        return match (true) {
            $invitation->isAccepted() => 'Accepted',
            $invitation->isRevoked() => 'Revoked',
            $invitation->isExpired() => 'Expired',
            default => 'Pending',
        };
    }

    protected function invitationStatusColor(string $status): string
    {
        return match ($status) {
            'Accepted' => 'success',
            'Revoked' => 'danger',
            'Expired' => 'warning',
            default => 'gray',
        };
    }

    protected function normalizeExpiresAt(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (is_string($value) && mb_trim($value) !== '') {
            return Carbon::parse($value);
        }

        return null;
    }
}
