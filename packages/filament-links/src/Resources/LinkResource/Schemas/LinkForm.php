<?php

declare(strict_types=1);

namespace AIArmada\FilamentLinks\Resources\LinkResource\Schemas;

use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Links\Models\Link;
use AIArmada\Links\Rules\DestinationUrlRule;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\Rule;

final class LinkForm
{
    public static function isProtectedSubject(?Link $link): bool
    {
        if ($link === null || ! is_string($link->subject_type) || $link->subject_type === '') {
            return false;
        }

        $subjectType = $link->subject_type;
        $subjectClass = Relation::getMorphedModel($subjectType) ?? $subjectType;

        $configuredTypes = config('filament-links.protected_subject_types');
        $subjectTypes = is_array($configuredTypes)
            ? $configuredTypes
            : (class_exists(AffiliateLink::class)
                ? [AffiliateLink::class]
                : []);

        foreach ($subjectTypes as $subjectType) {
            if (is_string($subjectType)
                && ($subjectType === $link->subject_type
                    || (class_exists($subjectType) && is_a($subjectClass, $subjectType, true)))) {
                return true;
            }
        }

        return false;
    }

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
                        ->helperText('Globally unique. Blank auto-generates on creation and keeps the current slug on edit.')
                        ->disabled(fn (?Link $record): bool => self::isProtectedSubject($record))
                        ->dehydrated(fn (?Link $record): bool => ! self::isProtectedSubject($record)),

                    Forms\Components\TextInput::make('destination_url')
                        ->label('Destination URL')
                        ->required()
                        ->maxLength(2000)
                        ->url()
                        ->rule(new DestinationUrlRule)
                        ->disabled(fn (?Link $record): bool => self::isProtectedSubject($record))
                        ->dehydrated(fn (?Link $record): bool => ! self::isProtectedSubject($record))
                        ->columnSpanFull(),
                ]),

            Section::make('UTM defaults')
                ->description('Merged into the destination on redirect. Incoming query values win.')
                ->collapsible()
                ->schema([
                    Forms\Components\TextInput::make('utm_defaults.utm_source')->maxLength(255)->disabled(fn (?Link $record): bool => self::isProtectedSubject($record))->dehydrated(fn (?Link $record): bool => ! self::isProtectedSubject($record)),
                    Forms\Components\TextInput::make('utm_defaults.utm_medium')->maxLength(255)->disabled(fn (?Link $record): bool => self::isProtectedSubject($record))->dehydrated(fn (?Link $record): bool => ! self::isProtectedSubject($record)),
                    Forms\Components\TextInput::make('utm_defaults.utm_campaign')->maxLength(255)->disabled(fn (?Link $record): bool => self::isProtectedSubject($record))->dehydrated(fn (?Link $record): bool => ! self::isProtectedSubject($record)),
                    Forms\Components\TextInput::make('utm_defaults.utm_content')->maxLength(255)->disabled(fn (?Link $record): bool => self::isProtectedSubject($record))->dehydrated(fn (?Link $record): bool => ! self::isProtectedSubject($record)),
                    Forms\Components\TextInput::make('utm_defaults.utm_term')->maxLength(255)->disabled(fn (?Link $record): bool => self::isProtectedSubject($record))->dehydrated(fn (?Link $record): bool => ! self::isProtectedSubject($record)),
                ])
                ->columns(2),

            Section::make('Destination parameters')
                ->description('Always merged into the destination on redirect. These win over incoming query values, so request URLs can never spoof them.')
                ->collapsible()
                ->schema([
                    Forms\Components\KeyValue::make('parameters')
                        ->keyLabel('Parameter')
                        ->valueLabel('Value')
                        ->disabled(fn (?Link $record): bool => self::isProtectedSubject($record))
                        ->dehydrated(fn (?Link $record): bool => ! self::isProtectedSubject($record))
                        ->columnSpanFull(),
                ]),

            Section::make('Signed URLs')
                ->description('Signed links only redirect with a valid, unexpired signature. Generate shareable URLs from the row Open action.')
                ->collapsible()
                ->schema([
                    Forms\Components\Toggle::make('require_signature')
                        ->label('Require signature')
                        ->helperText('Unsigned, tampered, or expired hits return 403 and record nothing.')
                        ->disabled(fn (?Link $record): bool => self::isProtectedSubject($record))
                        ->dehydrated(fn (?Link $record): bool => ! self::isProtectedSubject($record)),
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
