<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Schemas;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

final class SocialProfileFormSchema
{
    /**
     * @return array<int, mixed>
     */
    public static function make(): array
    {
        return [
            Section::make('Social Profile')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('platform')
                            ->label('Platform')
                            ->options(self::platformOptions())
                            ->required()
                            ->searchable()
                            ->native(false),

                        TextInput::make('label')
                            ->label('Label')
                            ->maxLength(255)
                            ->placeholder('Main page, Youth wing, Official channel'),
                    ]),

                    Grid::make(2)->schema([
                        TextInput::make('handle')
                            ->label('Handle')
                            ->maxLength(255)
                            ->placeholder('@example'),

                        TextInput::make('display_name')
                            ->label('Display Name')
                            ->maxLength(255),
                    ]),

                    TextInput::make('url')
                        ->label('URL')
                        ->url()
                        ->maxLength(2048)
                        ->placeholder('https://facebook.com/example'),

                    Grid::make(2)->schema([
                        Toggle::make('is_primary')
                            ->label('Primary'),

                        Toggle::make('is_public')
                            ->label('Public')
                            ->default(true),
                    ]),
                ]),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function platformOptions(): array
    {
        return config('contacting.social_profiles.platforms', [
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
            'youtube' => 'YouTube',
            'linkedin' => 'LinkedIn',
            'x' => 'X / Twitter',
            'threads' => 'Threads',
            'telegram' => 'Telegram',
            'telegram_channel' => 'Telegram Channel',
            'telegram_group' => 'Telegram Group',
            'whatsapp_channel' => 'WhatsApp Channel',
            'website' => 'Website',
            'other' => 'Other',
        ]);
    }
}
