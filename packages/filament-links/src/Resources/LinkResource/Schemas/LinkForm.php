<?php

declare(strict_types=1);

namespace AIArmada\FilamentLinks\Resources\LinkResource\Schemas;

use AIArmada\Links\Models\Link;
use AIArmada\Links\Rules\DestinationUrlRule;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

final class LinkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Link')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('slug')
                        ->minLength(3)
                        ->maxLength(100)
                        ->rules(['nullable', 'alpha_dash', Rule::notIn((array) config('links.features.security.reserved_slugs', []))])
                        ->unique(Link::class, column: 'slug', ignoreRecord: true)
                        ->helperText('Globally unique. Blank auto-generates on creation and keeps the current slug on edit.'),

                    Forms\Components\TextInput::make('destination_url')
                        ->label('Destination URL')
                        ->required()
                        ->maxLength(2000)
                        ->url()
                        ->rule(new DestinationUrlRule)
                        ->columnSpanFull(),
                ]),

            Section::make('UTM defaults')
                ->description('Merged into the destination on redirect. Incoming query values win.')
                ->collapsible()
                ->schema([
                    Forms\Components\TextInput::make('utm_defaults.utm_source')->maxLength(255),
                    Forms\Components\TextInput::make('utm_defaults.utm_medium')->maxLength(255),
                    Forms\Components\TextInput::make('utm_defaults.utm_campaign')->maxLength(255),
                    Forms\Components\TextInput::make('utm_defaults.utm_content')->maxLength(255),
                    Forms\Components\TextInput::make('utm_defaults.utm_term')->maxLength(255),
                ])
                ->columns(2),

            Section::make('Destination parameters')
                ->description('Always merged into the destination on redirect. These win over incoming query values, so request URLs can never spoof them.')
                ->collapsible()
                ->schema([
                    Forms\Components\KeyValue::make('parameters')
                        ->keyLabel('Parameter')
                        ->valueLabel('Value')
                        ->columnSpanFull(),
                ]),

            Section::make('Signed URLs')
                ->description('Signed links only redirect with a valid, unexpired signature. Generate shareable URLs from the row Open action.')
                ->collapsible()
                ->schema([
                    Forms\Components\Toggle::make('require_signature')
                        ->label('Require signature')
                        ->helperText('Unsigned, tampered, or expired hits return 403 and record nothing.'),
                ]),

            Section::make('Limits')
                ->collapsible()
                ->schema([
                    Forms\Components\TextInput::make('max_clicks')
                        ->label('Maximum clicks')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Counts human clicks. Bots never exhaust a link.'),

                    Forms\Components\DateTimePicker::make('expires_at')
                        ->label('Expires at')
                        ->native(false),
                ])
                ->columns(2),
        ]);
    }
}
