<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrganizations\RelationManagers;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\Membership\Enums\MemberRole;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Foundation\Auth\User;

abstract class AbstractMembersRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'members';

    protected static ?string $title = 'Members';

    public function table(Table $table): Table
    {
        return $table
            ->columns($this->getMemberColumns())
            ->headerActions([
                Action::make('addMember')
                    ->label('Add member')
                    ->form([
                        Select::make('user_id')
                            ->label('User')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => $this->searchUsers($search))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => $this->getUserOptionLabel($value))
                            ->required(),
                        $this->makeRoleSelect(),
                    ])
                    ->action(function (array $data): void {
                        $owner = $this->getOwnerRecord();
                        $this->authorizeMutation('add');

                        $userClass = $this->userModelClass();
                        /** @var Model $member */
                        $member = $userClass::query()->findOrFail($data['user_id']);
                        $role = (string) ($data['role_id'] ?? $this->defaultAddRole());

                        $this->addMember($owner, $member, $role);
                        $this->afterMemberMutated();
                    }),
            ])
            ->actions([
                Action::make('manageRoles')
                    ->label('Role')
                    ->hidden(fn (Model $record): bool => $this->memberHasProtectedRole($record))
                    ->form([
                        $this->makeRoleSelect(),
                    ])
                    ->fillForm(fn (Model $record): array => [
                        'role_id' => $this->getMemberRoleValue($record),
                    ])
                    ->action(function (array $data, Model $record): void {
                        $owner = $this->getOwnerRecord();
                        $this->authorizeMutation('changeRole');

                        $role = (string) ($data['role_id'] ?? MemberRole::Viewer->value);
                        $this->changeMemberRole($owner, $record, $role);
                        $this->afterMemberMutated();
                    }),
                Action::make('removeMember')
                    ->label('Remove')
                    ->color('danger')
                    ->hidden(fn (Model $record): bool => $this->memberHasProtectedRole($record))
                    ->requiresConfirmation()
                    ->action(function (Model $record): void {
                        $owner = $this->getOwnerRecord();
                        $this->authorizeMutation('remove');

                        $this->removeMember($owner, $record);
                        $this->afterMemberMutated();
                    }),
            ]);
    }

    abstract protected function addMember(Model $owner, Model $member, string $role): void;

    abstract protected function changeMemberRole(Model $owner, Model $member, string $role): void;

    abstract protected function removeMember(Model $owner, Model $member): void;

    abstract protected function authorizeMutation(string $ability): void;

    protected function afterMemberMutated(): void {}

    /**
     * @return class-string<Model>
     */
    protected function userModelClass(): string
    {
        $configured = config('auth.providers.users.model');

        if (is_string($configured) && class_exists($configured) && is_subclass_of($configured, Model::class)) {
            return $configured;
        }

        /** @var class-string<Model> $fallback */
        $fallback = User::class;

        return $fallback;
    }

    /**
     * @return array<int, TextColumn>
     */
    protected function getMemberColumns(): array
    {
        return [
            TextColumn::make('name')
                ->searchable()
                ->sortable()
                ->url(fn (Model $record): ?string => $this->memberNameUrl($record)),
            TextColumn::make('email')
                ->searchable()
                ->sortable(),
            TextColumn::make('pivot.role')
                ->label('Role')
                ->badge()
                ->getStateUsing(fn (Model $record): string => $this->getMemberRoleValue($record) ?? '—'),
        ];
    }

    protected function memberNameUrl(Model $record): ?string
    {
        return null;
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

    protected function defaultAddRole(): string
    {
        return MemberRole::Viewer->value;
    }

    protected function makeRoleSelect(): Select
    {
        return Select::make('role_id')
            ->label('Role')
            ->options(fn (): array => $this->roleOptions())
            ->default($this->defaultAddRole())
            ->required();
    }

    protected function getMemberRoleValue(Model $member): ?string
    {
        $pivot = $member->relationLoaded('pivot') ? $member->getRelation('pivot') : $member->getRelationValue('pivot');

        if ($pivot instanceof Pivot) {
            $role = $pivot->getAttribute('role');

            return is_string($role) && $role !== '' ? $role : null;
        }

        return null;
    }

    protected function memberHasProtectedRole(Model $member): bool
    {
        $role = $this->getMemberRoleValue($member);

        return in_array($role, [MemberRole::Owner->value, MemberRole::Owner->spatieRoleName()], true);
    }

    /**
     * @return array<string, string>
     */
    protected function searchUsers(string $search): array
    {
        $this->authorizeMutation('add');
        $trimmed = mb_trim($search);
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($trimmed));
        $userClass = $this->userModelClass();

        return $userClass::query()
            ->when($escaped !== '', static function (Builder $query) use ($escaped): void {
                $query->where(static function (Builder $nested) use ($escaped): void {
                    $nested->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                        ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", ["%{$escaped}%"]);
                });
            })
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'email'])
            ->mapWithKeys(static fn (Model $user): array => [
                (string) $user->getKey() => sprintf('%s (%s)', (string) $user->getAttribute('name'), (string) $user->getAttribute('email')),
            ])
            ->all();
    }

    protected function getUserOptionLabel(mixed $value): ?string
    {
        $this->authorizeMutation('add');

        if (! is_string($value) || $value === '') {
            return null;
        }

        $userClass = $this->userModelClass();
        $user = $userClass::query()->find($value, ['id', 'name', 'email']);

        if (! $user instanceof Model) {
            return null;
        }

        return sprintf('%s (%s)', (string) $user->getAttribute('name'), (string) $user->getAttribute('email'));
    }
}
