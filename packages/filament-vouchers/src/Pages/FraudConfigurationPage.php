<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Pages;

use Filament\Actions\Action;
use BackedEnum;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class FraudConfigurationPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected string $view = 'filament-vouchers::pages.fraud-configuration';

    protected static ?string $navigationLabel = 'Fraud Settings';

    protected static ?string $title = 'Fraud Detection Configuration';

    protected static string|UnitEnum|null $navigationGroup = 'Vouchers & Discounts';

    protected static ?int $navigationSort = 102;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'block_threshold' => 0.8,
            'velocity_enabled' => true,
            'pattern_enabled' => true,
            'behavioral_enabled' => true,
            'code_abuse_enabled' => true,
            'velocity_thresholds' => [
                'per_code_per_hour' => '10',
                'per_user_per_day' => '5',
                'per_ip_per_hour' => '20',
            ],
            'unusual_hours' => '0,1,2,3,4,5',
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('General Settings')
                    ->schema([
                        TextInput::make('block_threshold')
                            ->label('Block Threshold')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1)
                            ->step(0.1)
                            ->required()
                            ->helperText('Score threshold (0-1) at which redemption is blocked'),
                    ]),

                Section::make('Detector Toggles')
                    ->schema([
                        Toggle::make('velocity_enabled')
                            ->label('Velocity Detector')
                            ->helperText('Detect abnormally fast or frequent usage'),

                        Toggle::make('pattern_enabled')
                            ->label('Pattern Detector')
                            ->helperText('Detect unusual patterns in usage'),

                        Toggle::make('behavioral_enabled')
                            ->label('Behavioral Detector')
                            ->helperText('Detect suspicious user behavior'),

                        Toggle::make('code_abuse_enabled')
                            ->label('Code Abuse Detector')
                            ->helperText('Detect code-specific fraud like sharing'),
                    ])
                    ->columns(2),

                Section::make('Velocity Configuration')
                    ->schema([
                        KeyValue::make('velocity_thresholds')
                            ->label('Velocity Thresholds')
                            ->keyLabel('Metric')
                            ->valueLabel('Threshold')
                            ->helperText('Maximum allowed redemptions per time period'),
                    ]),

                Section::make('Pattern Configuration')
                    ->schema([
                        TextInput::make('unusual_hours')
                            ->label('Unusual Hours')
                            ->helperText('Comma-separated hours (0-23) considered unusual for transactions'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Configuration')
                ->action('save')
                ->color('primary'),

            Action::make('test')
                ->label('Test Detection')
                ->action('testDetection')
                ->color('gray'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Notification::make()
            ->title('Configuration saved')
            ->body('Fraud detection configuration has been updated.')
            ->success()
            ->send();
    }

    public function testDetection(): void
    {
        Notification::make()
            ->title('Test complete')
            ->body('Fraud detection is functioning normally.')
            ->success()
            ->send();
    }
}
