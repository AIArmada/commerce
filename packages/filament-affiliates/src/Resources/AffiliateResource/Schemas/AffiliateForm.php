<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateResource\Schemas;

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\Affiliates\States\Draft;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use BackedEnum;
use Closure;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Auth\User;

final class AffiliateForm
{
    public static function configure(Schema $schema): Schema
    {
        $currency = (string) config('affiliates.currency.default', 'USD');

        return $schema->components([
            Section::make('Affiliate Details')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('code')
                            ->label('Tracking Code')
                            ->required()
                            ->maxLength(64)
                            ->rule(fn (mixed $component): Closure => function (string $attribute, string $value, Closure $fail) use ($component): void {
                                $query = Affiliate::query()
                                    ->whereRaw('LOWER(code) = ?', [mb_strtolower($value)]);

                                if (method_exists($component, 'getRecord') && $record = $component->getRecord()) {
                                    $query->whereKeyNot($record->getKey());
                                }

                                if ($query->exists()) {
                                    $fail('The code has already been taken.');
                                }
                            }),

                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(120),

                        Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options(AffiliateStatus::options())
                            ->default(AffiliateStatus::fromString(Draft::class)->getValue()),

                        Select::make('parent_affiliate_id')
                            ->label('Parent Affiliate')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->preload(),

                        Select::make('rank_id')
                            ->label('Rank')
                            ->relationship('rank', 'name')
                            ->searchable()
                            ->preload(),
                    ]),

                    Textarea::make('description')
                        ->label('Description')
                        ->rows(3),

                    Grid::make(3)->schema([
                        TextInput::make('default_voucher_code')
                            ->label('Default Voucher Code')
                            ->maxLength(64),

                        TextInput::make('tracking_domain')
                            ->label('Tracking Domain')
                            ->placeholder('track.example.com'),

                        TextInput::make('payout_terms')
                            ->label('Payout Terms')
                            ->placeholder('Net-30'),
                    ]),
                ]),

            Section::make('Commission Policy')
                ->schema([
                    Grid::make(3)->schema([
                        Select::make('commission_type')
                            ->label('Type')
                            ->required()
                            ->options(self::enumOptions(CommissionType::class))
                            ->default(CommissionType::Percentage->value),

                        TextInput::make('commission_rate')
                            ->label('Rate')
                            ->numeric()
                            ->required()
                            ->suffix(fn (Get $get): string => $get('commission_type') === CommissionType::Percentage->value ? '%' : $get('currency') ?? $currency)
                            ->formatStateUsing(fn (?int $state, Get $get): ?string => $state === null
                                ? null
                                : (
                                    $get('commission_type') === CommissionType::Percentage->value
                                    ? number_format($state / 100, 2, '.', '')
                                    : MoneyFormatter::decimalFromMinor($state, (string) ($get('currency') ?? $currency))
                                ))
                            ->dehydrateStateUsing(
                                fn (?string $state, Get $get): ?int => $state === null || $state === ''
                                ? null
                                : (int) round((float) $state * 100)
                            ),

                        Select::make('currency')
                            ->label('Currency')
                            ->options([
                                'USD' => 'USD',
                                'MYR' => 'MYR',
                                'SGD' => 'SGD',
                                'IDR' => 'IDR',
                            ])
                            ->default($currency),
                    ]),
                ])
                ->collapsible(),

            Section::make('Communication')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('contact_email')
                            ->label('Contact Email')
                            ->email()
                            ->maxLength(120),

                        TextInput::make('website_url')
                            ->label('Website')
                            ->url()
                            ->maxLength(255),
                    ]),
                ])
                ->collapsed(),

            Section::make('Portal Access')
                ->description('Link a user to this affiliate for self-service portal access.')
                ->schema([
                    Hidden::make('owner_type'),
                    Hidden::make('owner_id'),
                    Grid::make(1)->schema([
                        Select::make('linked_user')
                            ->label('Linked User')
                            ->searchable()
                            ->placeholder('No linked user')
                            ->helperText('Leave empty for admin-managed affiliates without portal access.')
                            ->getSearchResultsUsing(function (string $search): array {
                                $userModel = config('auth.providers.users.model', User::class);

                                return $userModel::where('email', 'like', "%{$search}%")
                                    ->orWhere('name', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->pluck('email', 'id')
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                $userModel = config('auth.providers.users.model', User::class);

                                return $userModel::find($value)?->email;
                            })
                            ->dehydrated(false)
                            ->afterStateHydrated(function (Select $component): void {
                                $record = $component->getRecord();
                                $userModelClass = (string) config('auth.providers.users.model', User::class);
                                $userMorphClass = (new $userModelClass)->getMorphClass();

                                if ($record instanceof Affiliate && $record->owner_type === $userMorphClass && $record->owner_id) {
                                    $component->state($record->owner_id);
                                }
                            })
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if ($state) {
                                    $userModelClass = (string) config('auth.providers.users.model', User::class);
                                    $set('owner_type', (new $userModelClass)->getMorphClass());
                                    $set('owner_id', $state);
                                } else {
                                    $set('owner_type', null);
                                    $set('owner_id', null);
                                }
                            }),
                    ]),
                ])
                ->collapsible(),

            Section::make('Metadata')
                ->schema([
                    KeyValue::make('metadata')
                        ->label('Metadata')
                        ->keyLabel('Key')
                        ->valueLabel('Value')
                        ->addActionLabel('Add entry'),
                ])
                ->collapsed(),
        ]);
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     * @return array<string, string>
     */
    private static function enumOptions(string $enum): array
    {
        /** @var array<int, BackedEnum> $cases */
        $cases = $enum::cases();

        return collect($cases)
            ->mapWithKeys(static fn ($case): array => [$case->value => method_exists($case, 'label') ? $case->label() : ucfirst($case->value)])
            ->toArray();
    }
}
