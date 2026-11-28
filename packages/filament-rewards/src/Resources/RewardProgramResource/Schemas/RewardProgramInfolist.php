<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources\RewardProgramResource\Schemas;

use AIArmada\FilamentRewards\Support\RewardAdminPresenter;
use AIArmada\Rewards\Features\Enrollment\Api\RewardEnrollmentConsentApi;
use AIArmada\Rewards\Features\Programs\Models\RewardProgram;
use AIArmada\Rewards\Features\Programs\Api\RewardProgramDirectoryApi;
use AIArmada\Rewards\Features\Programs\Api\RewardVersionSummary;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RewardProgramInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Program')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('title'),
                        TextEntry::make('family_key'),
                        TextEntry::make('mode')
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => RewardAdminPresenter::programModeOptions()[(string) $state->value] ?? (string) $state->value),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => RewardAdminPresenter::statusLabel($state))
                            ->color(fn (mixed $state): string => RewardAdminPresenter::statusColor($state)),
                        TextEntry::make('purpose')->columnSpan(2),
                        TextEntry::make('ready_at')->dateTime()->placeholder('-'),
                        TextEntry::make('active_at')->dateTime()->placeholder('-'),
                        TextEntry::make('paused_at')->dateTime()->placeholder('-'),
                        TextEntry::make('closed_at')->dateTime()->placeholder('-'),
                    ]),
                ]),
            Section::make('Current version')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('current_version')
                            ->label('Version')
                            ->state(fn (RewardProgram $record): string => self::currentVersionLabel($record)),
                        TextEntry::make('version_effective_from')
                            ->label('Effective from')
                            ->state(fn (RewardProgram $record): ?string => self::currentVersionEffectiveFrom($record))
                            ->placeholder('-'),
                        TextEntry::make('material_terms_hash')
                            ->label('Material terms hash')
                            ->state(fn (RewardProgram $record): ?string => self::currentVersionHash($record))
                            ->placeholder('-'),
                        TextEntry::make('version_count')
                            ->label('Published versions')
                            ->state(fn (RewardProgram $record): int => app(RewardProgramDirectoryApi::class)->versionCount((string) $record->getKey())),
                        TextEntry::make('enrollment_count')
                            ->label('Enrollments')
                            ->state(fn (RewardProgram $record): int => app(RewardEnrollmentConsentApi::class)->countForProgram((string) $record->getKey())),
                    ]),
                ]),
        ]);
    }

    private static function currentVersionLabel(RewardProgram $record): string
    {
        $version = self::currentVersion($record);

        return $version !== null ? 'v' . $version->version : 'None published';
    }

    private static function currentVersionEffectiveFrom(RewardProgram $record): ?string
    {
        return self::currentVersion($record)?->effectiveFrom;
    }

    private static function currentVersionHash(RewardProgram $record): ?string
    {
        return self::currentVersion($record)?->materialTermsHash;
    }

    private static function currentVersion(RewardProgram $record): ?RewardVersionSummary
    {
        return app(RewardProgramDirectoryApi::class)->currentVersion((string) $record->getKey());
    }
}
