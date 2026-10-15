<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Resources\ReferralProgramResource\Schemas;

use AIArmada\FilamentReferrals\Support\ReferralAdminPresenter;
use AIArmada\Referrals\Enums\ReferralProgramStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ReferralProgramForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Program')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(191),
                    TextInput::make('slug')
                        ->required()
                        ->maxLength(191),
                    // Status is a lifecycle field, not an edit field: it moves
                    // only through the matrix-checked table actions. On edit it
                    // is shown read-only and withheld from the payload, so a
                    // tampered request cannot reactivate an archived program.
                    Select::make('status')
                        ->options(self::programStatusOptions())
                        ->required()
                        ->disabled(fn (string $operation): bool => $operation === 'edit')
                        ->dehydrated(fn (string $operation): bool => $operation !== 'edit'),
                    TextInput::make('qualifier_outcome')
                        ->required()
                        ->maxLength(191),
                    TextInput::make('invite_expiry_days')
                        ->numeric()
                        ->rules(['integer', 'min:1'])
                        ->nullable(),
                    TextInput::make('max_invites_per_referrer')
                        ->numeric()
                        ->rules(['integer', 'min:0'])
                        ->nullable(),
                    TextInput::make('max_qualified_per_referrer_per_month')
                        ->numeric()
                        ->rules(['integer', 'min:0'])
                        ->nullable(),
                    TextInput::make('currency')
                        ->required()
                        ->default(config('referrals.defaults.currency', 'MYR'))
                        ->minLength(3)
                        ->maxLength(3),
                    DateTimePicker::make('starts_at')
                        ->seconds(false)
                        ->native(false)
                        ->nullable(),
                    DateTimePicker::make('ends_at')
                        ->seconds(false)
                        ->native(false)
                        ->nullable(),
                ])
                ->columns(2),
            Section::make('Referrer reward')
                ->schema(self::rewardFields('referrer_reward')),
            Section::make('Referee reward')
                ->schema(self::rewardFields('referee_reward')),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function programStatusOptions(): array
    {
        $statuses = ReferralProgramStatus::cases();

        return array_combine(
            array_map(static fn (ReferralProgramStatus $status): string => $status->value, $statuses),
            array_map(static fn (ReferralProgramStatus $status): string => ReferralAdminPresenter::statusLabel($status), $statuses),
        );
    }

    /**
     * @return array<int, Fieldset>
     */
    private static function rewardFields(string $side): array
    {
        return [
            Fieldset::make('Reward specification')
                ->schema([
                    Select::make($side . '.driver')
                        ->options(fn (): array => ReferralAdminPresenter::driverOptions())
                        ->searchable()
                        ->nullable()
                        ->live(),
                    TextInput::make($side . '.amount_minor')
                        ->numeric()
                        ->rules(['integer', 'min:0'])
                        ->nullable(),
                    TextInput::make($side . '.points')
                        ->numeric()
                        ->rules(['integer', 'min:1'])
                        ->nullable(),
                    TextInput::make($side . '.family_key')
                        ->label('Rewards family key')
                        ->helperText('Binds the points promise to one rewards program family.')
                        ->rule('regex:/^[a-z0-9][a-z0-9_\-]{0,63}$/')
                        ->maxLength(64)
                        ->nullable()
                        ->visible(fn (Get $get): bool => $get($side . '.driver') === 'reward_points'),
                    TextInput::make($side . '.currency')
                        ->minLength(3)
                        ->maxLength(3)
                        ->nullable(),
                    TextInput::make($side . '.voucher.type')
                        ->label('Voucher type')
                        ->required(fn (Get $get): bool => $get($side . '.driver') === 'voucher')
                        ->visible(fn (Get $get): bool => $get($side . '.driver') === 'voucher'),
                    TextInput::make($side . '.voucher.value')
                        ->label('Voucher value (minor units)')
                        ->numeric()
                        ->rules(['integer', 'min:0'])
                        ->nullable()
                        ->visible(fn (Get $get): bool => $get($side . '.driver') === 'voucher'),
                ])
                ->columns(2),
        ];
    }
}
