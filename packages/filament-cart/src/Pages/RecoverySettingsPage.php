<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Pages;

use AIArmada\FilamentCart\Settings\CartRecoverySettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Throwable;

/**
 * Recovery settings configuration page.
 */
class RecoverySettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Recovery Settings';

    protected static ?string $title = 'Cart Recovery Settings';

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament-cart::pages.recovery-settings';

    public function mount(): void
    {
        $settings = $this->resolveSettings();

        $this->form->fill($settings
            ? $this->mapSettingsToFormState($settings)
            : $this->getDefaultValues());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('Settings')
                    ->tabs([
                        Tab::make('General')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Section::make('Recovery Defaults')
                                    ->description('Default settings for cart recovery campaigns')
                                    ->schema([
                                        Toggle::make('recovery_enabled')
                                            ->label('Enable Recovery')
                                            ->helperText('Master switch for cart recovery')
                                            ->default(true),

                                        TextInput::make('default_abandonment_threshold')
                                            ->label('Abandonment Threshold (minutes)')
                                            ->numeric()
                                            ->default(60)
                                            ->minValue(15)
                                            ->maxValue(1440)
                                            ->suffix('minutes')
                                            ->helperText('Cart is considered abandoned after this time'),

                                        TextInput::make('max_recovery_attempts')
                                            ->label('Max Recovery Attempts')
                                            ->numeric()
                                            ->default(3)
                                            ->minValue(1)
                                            ->maxValue(10)
                                            ->helperText('Maximum attempts per abandoned cart'),

                                        TextInput::make('cooldown_between_attempts')
                                            ->label('Cooldown Between Attempts (hours)')
                                            ->numeric()
                                            ->default(24)
                                            ->minValue(1)
                                            ->maxValue(168)
                                            ->suffix('hours'),
                                    ])
                                    ->columns(2),
                            ]),

                        Tab::make('Email')
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                Section::make('Email Configuration')
                                    ->description('Settings for email recovery channel')
                                    ->schema([
                                        Toggle::make('email_enabled')
                                            ->label('Enable Email Recovery')
                                            ->default(true),

                                        TextInput::make('email_from_name')
                                            ->label('From Name')
                                            ->default(config('app.name')),

                                        TextInput::make('email_from_address')
                                            ->label('From Email')
                                            ->email()
                                            ->default(config('mail.from.address')),

                                        TextInput::make('email_reply_to')
                                            ->label('Reply-To Email')
                                            ->email()
                                            ->placeholder('Leave empty for default'),

                                        Toggle::make('email_track_opens')
                                            ->label('Track Opens')
                                            ->helperText('Add tracking pixel to emails')
                                            ->default(true),

                                        Toggle::make('email_track_clicks')
                                            ->label('Track Clicks')
                                            ->helperText('Track link clicks in emails')
                                            ->default(true),
                                    ])
                                    ->columns(2),
                            ]),

                        Tab::make('SMS')
                            ->icon('heroicon-o-device-phone-mobile')
                            ->schema([
                                Section::make('SMS Configuration')
                                    ->description('Settings for SMS recovery channel')
                                    ->schema([
                                        Toggle::make('sms_enabled')
                                            ->label('Enable SMS Recovery')
                                            ->default(false),

                                        Select::make('sms_provider')
                                            ->label('SMS Provider')
                                            ->options([
                                                'twilio' => 'Twilio',
                                                'vonage' => 'Vonage (Nexmo)',
                                                'messagebird' => 'MessageBird',
                                            ])
                                            ->placeholder('Select provider'),

                                        TextInput::make('sms_from_number')
                                            ->label('From Number')
                                            ->tel()
                                            ->placeholder('+1234567890'),

                                        TextInput::make('sms_max_length')
                                            ->label('Max Message Length')
                                            ->numeric()
                                            ->default(160)
                                            ->minValue(50)
                                            ->maxValue(1600),
                                    ])
                                    ->columns(2),
                            ]),

                        Tab::make('Push')
                            ->icon('heroicon-o-bell-alert')
                            ->schema([
                                Section::make('Push Notification Configuration')
                                    ->description('Settings for push notification recovery channel')
                                    ->schema([
                                        Toggle::make('push_enabled')
                                            ->label('Enable Push Recovery')
                                            ->default(false),

                                        Select::make('push_provider')
                                            ->label('Push Provider')
                                            ->options([
                                                'firebase' => 'Firebase Cloud Messaging',
                                                'onesignal' => 'OneSignal',
                                                'pusher' => 'Pusher Beams',
                                            ])
                                            ->placeholder('Select provider'),

                                        TextInput::make('push_icon_url')
                                            ->label('Notification Icon URL')
                                            ->url()
                                            ->placeholder('https://example.com/icon.png'),

                                        Toggle::make('push_require_interaction')
                                            ->label('Require Interaction')
                                            ->helperText('Notification stays until user interacts')
                                            ->default(false),
                                    ])
                                    ->columns(2),
                            ]),

                        Tab::make('Timing')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                Section::make('Timing Rules')
                                    ->description('Control when recovery messages are sent')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('send_start_hour')
                                                    ->label('Send Window Start')
                                                    ->numeric()
                                                    ->default(9)
                                                    ->minValue(0)
                                                    ->maxValue(23)
                                                    ->suffix('hour (24h format)'),

                                                TextInput::make('send_end_hour')
                                                    ->label('Send Window End')
                                                    ->numeric()
                                                    ->default(21)
                                                    ->minValue(0)
                                                    ->maxValue(23)
                                                    ->suffix('hour (24h format)'),
                                            ]),

                                        Toggle::make('respect_user_timezone')
                                            ->label('Respect User Timezone')
                                            ->helperText('Send in user\'s local time zone')
                                            ->default(true),

                                        Repeater::make('blocked_days')
                                            ->label('Blocked Days')
                                            ->schema([
                                                Select::make('day')
                                                    ->options([
                                                        'monday' => 'Monday',
                                                        'tuesday' => 'Tuesday',
                                                        'wednesday' => 'Wednesday',
                                                        'thursday' => 'Thursday',
                                                        'friday' => 'Friday',
                                                        'saturday' => 'Saturday',
                                                        'sunday' => 'Sunday',
                                                    ])
                                                    ->required(),
                                            ])
                                            ->columns(1)
                                            ->defaultItems(0)
                                            ->addActionLabel('Add Blocked Day'),
                                    ]),
                            ]),

                        Tab::make('Exclusions')
                            ->icon('heroicon-o-x-circle')
                            ->schema([
                                Section::make('Exclusion Rules')
                                    ->description('Define when NOT to send recovery messages')
                                    ->schema([
                                        TextInput::make('min_cart_value')
                                            ->label('Minimum Cart Value')
                                            ->numeric()
                                            ->prefix('$')
                                            ->default(0)
                                            ->helperText('Skip carts below this value'),

                                        TextInput::make('max_messages_per_customer_per_week')
                                            ->label('Max Messages Per Customer Per Week')
                                            ->numeric()
                                            ->default(3)
                                            ->minValue(1)
                                            ->maxValue(10),

                                        Toggle::make('exclude_repeat_recoveries')
                                            ->label('Exclude Recently Recovered')
                                            ->helperText('Skip customers recovered in last 30 days')
                                            ->default(true),

                                        TextInput::make('exclude_if_ordered_within_days')
                                            ->label('Exclude If Ordered Within (days)')
                                            ->numeric()
                                            ->default(7)
                                            ->helperText('Skip if customer ordered recently'),

                                        KeyValue::make('custom_exclusion_rules')
                                            ->label('Custom Exclusion Rules')
                                            ->helperText('Custom field-value pairs for exclusion')
                                            ->keyLabel('Field')
                                            ->valueLabel('Value'),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = $this->resolveSettings();

        if ($settings !== null) {
            $this->applyFormStateToSettings($settings, $data);
            $settings->save();
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    public function resetToDefaults(): void
    {
        $defaults = $this->getDefaultValues();
        $this->form->fill($defaults);

        $settings = $this->resolveSettings();

        if ($settings !== null) {
            $this->applyFormStateToSettings($settings, $defaults);
            $settings->save();
        }

        Notification::make()
            ->title('Settings reset to defaults')
            ->info()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->action('save')
                ->icon('heroicon-o-check'),

            Action::make('reset')
                ->label('Reset to Defaults')
                ->color('gray')
                ->action('resetToDefaults')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Reset Settings')
                ->modalDescription('Are you sure you want to reset all settings to their default values?'),
        ];
    }

    private function getDefaultValues(): array
    {
        return [
            'recovery_enabled' => true,
            'default_abandonment_threshold' => 60,
            'max_recovery_attempts' => 3,
            'cooldown_between_attempts' => 24,

            'email_enabled' => true,
            'email_from_name' => config('app.name'),
            'email_from_address' => config('mail.from.address'),
            'email_reply_to' => null,
            'email_track_opens' => true,
            'email_track_clicks' => true,

            'sms_enabled' => false,
            'sms_provider' => null,
            'sms_from_number' => null,
            'sms_max_length' => 160,

            'push_enabled' => false,
            'push_provider' => null,
            'push_icon_url' => null,
            'push_require_interaction' => false,

            'send_start_hour' => 9,
            'send_end_hour' => 21,
            'respect_user_timezone' => true,
            'blocked_days' => [],

            'min_cart_value' => 0,
            'max_messages_per_customer_per_week' => 3,
            'exclude_repeat_recoveries' => true,
            'exclude_if_ordered_within_days' => 7,
            'custom_exclusion_rules' => [],
        ];
    }

    private function resolveSettings(): ?CartRecoverySettings
    {
        try {
            /** @var CartRecoverySettings $settings */
            $settings = app(CartRecoverySettings::class);

            return $settings;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSettingsToFormState(CartRecoverySettings $settings): array
    {
        return [
            'recovery_enabled' => $settings->recoveryEnabled,
            'default_abandonment_threshold' => $settings->defaultAbandonmentThresholdMinutes,
            'max_recovery_attempts' => $settings->maxRecoveryAttempts,
            'cooldown_between_attempts' => $settings->cooldownBetweenAttemptsHours,
            'email_enabled' => $settings->emailEnabled,
            'email_from_name' => $settings->emailFromName,
            'email_from_address' => $settings->emailFromAddress,
            'email_reply_to' => $settings->emailReplyTo,
            'email_track_opens' => $settings->emailTrackOpens,
            'email_track_clicks' => $settings->emailTrackClicks,
            'sms_enabled' => $settings->smsEnabled,
            'sms_provider' => $settings->smsProvider,
            'sms_from_number' => $settings->smsFromNumber,
            'sms_max_length' => $settings->smsMaxLength,
            'push_enabled' => $settings->pushEnabled,
            'push_provider' => $settings->pushProvider,
            'push_icon_url' => $settings->pushIconUrl,
            'push_require_interaction' => $settings->pushRequireInteraction,
            'send_start_hour' => $settings->sendStartHour,
            'send_end_hour' => $settings->sendEndHour,
            'respect_user_timezone' => $settings->respectUserTimezone,
            'blocked_days' => $settings->blockedDays,
            'min_cart_value' => (int) round($settings->minCartValue / 100),
            'max_messages_per_customer_per_week' => $settings->maxMessagesPerCustomerPerWeek,
            'exclude_repeat_recoveries' => $settings->excludeRepeatRecoveries,
            'exclude_if_ordered_within_days' => $settings->excludeIfOrderedWithinDays,
            'custom_exclusion_rules' => $settings->customExclusionRules,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyFormStateToSettings(CartRecoverySettings $settings, array $data): void
    {
        $settings->recoveryEnabled = (bool) ($data['recovery_enabled'] ?? true);
        $settings->defaultAbandonmentThresholdMinutes = (int) ($data['default_abandonment_threshold'] ?? 60);
        $settings->maxRecoveryAttempts = (int) ($data['max_recovery_attempts'] ?? 3);
        $settings->cooldownBetweenAttemptsHours = (int) ($data['cooldown_between_attempts'] ?? 24);
        $settings->emailEnabled = (bool) ($data['email_enabled'] ?? true);
        $settings->emailFromName = (string) ($data['email_from_name'] ?? config('app.name'));
        $settings->emailFromAddress = (string) ($data['email_from_address'] ?? config('mail.from.address'));
        $settings->emailReplyTo = $data['email_reply_to'] !== '' ? $data['email_reply_to'] : null;
        $settings->emailTrackOpens = (bool) ($data['email_track_opens'] ?? true);
        $settings->emailTrackClicks = (bool) ($data['email_track_clicks'] ?? true);
        $settings->smsEnabled = (bool) ($data['sms_enabled'] ?? false);
        $settings->smsProvider = $data['sms_provider'] !== '' ? $data['sms_provider'] : null;
        $settings->smsFromNumber = $data['sms_from_number'] !== '' ? $data['sms_from_number'] : null;
        $settings->smsMaxLength = (int) ($data['sms_max_length'] ?? 160);
        $settings->pushEnabled = (bool) ($data['push_enabled'] ?? false);
        $settings->pushProvider = $data['push_provider'] !== '' ? $data['push_provider'] : null;
        $settings->pushIconUrl = $data['push_icon_url'] !== '' ? $data['push_icon_url'] : null;
        $settings->pushRequireInteraction = (bool) ($data['push_require_interaction'] ?? false);
        $settings->sendStartHour = (int) ($data['send_start_hour'] ?? 9);
        $settings->sendEndHour = (int) ($data['send_end_hour'] ?? 21);
        $settings->respectUserTimezone = (bool) ($data['respect_user_timezone'] ?? true);
        $settings->blockedDays = is_array($data['blocked_days'] ?? null) ? $data['blocked_days'] : [];
        $settings->minCartValue = (int) round(((float) ($data['min_cart_value'] ?? 0)) * 100);
        $settings->maxMessagesPerCustomerPerWeek = (int) ($data['max_messages_per_customer_per_week'] ?? 3);
        $settings->excludeRepeatRecoveries = (bool) ($data['exclude_repeat_recoveries'] ?? true);
        $settings->excludeIfOrderedWithinDays = (int) ($data['exclude_if_ordered_within_days'] ?? 7);
        $settings->customExclusionRules = is_array($data['custom_exclusion_rules'] ?? null)
            ? $data['custom_exclusion_rules']
            : [];
    }
}
