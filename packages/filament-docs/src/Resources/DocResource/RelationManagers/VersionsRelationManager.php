<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\RelationManagers;

use AIArmada\Docs\Models\DocVersion;
use AIArmada\FilamentDocs\Support\DocsOwnerScope;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $recordTitleAttribute = 'version_number';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version_number')
            ->columns([
                TextColumn::make('version_number')
                    ->label('Version')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('change_summary')
                    ->label('Summary')
                    ->limit(50),

                TextColumn::make('changed_by')
                    ->label('Changed By'),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->modalContent(function (DocVersion $record): View {
                        $doc = $record->doc;

                        if ($doc === null) {
                            throw new NotFoundHttpException('Document not found.');
                        }

                        DocsOwnerScope::assertCanAccessDoc($doc);

                        return view('filament-docs::partials.version-snapshot', [
                            'snapshot' => $record->snapshot,
                        ]);
                    }),
                Action::make('restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Restore Version')
                    ->modalDescription('Are you sure you want to restore this version? Current data will be overwritten.')
                    ->action(function (DocVersion $record): void {
                        $doc = $record->doc;

                        if ($doc === null) {
                            throw new NotFoundHttpException('Document not found.');
                        }

                        DocsOwnerScope::assertCanMutateDoc($doc);
                        $record->restore();
                        $this->dispatch('refresh');
                    }),
            ])
            ->defaultSort('version_number', 'desc');
    }
}
