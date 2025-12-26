<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocTemplateResource\Tables;

use AIArmada\Docs\Models\DocTemplate;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

final class DocTemplatesTable
{
    public static function configure(Table $table): Table
    {
        $docTypes = config('docs.types', []);

        if (! is_array($docTypes)) {
            $docTypes = [];
        }

        $docTypeOptions = collect($docTypes)
            ->keys()
            ->mapWithKeys(static fn (string $type): array => [$type => Str::headline($type)])
            ->all();

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('doc_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->sortable(),

                TextColumn::make('view_name')
                    ->label('View')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('docs_count')
                    ->label('Documents')
                    ->counts('docs')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('doc_type')
                    ->label('Document Type')
                    ->options($docTypeOptions),

                TernaryFilter::make('is_default')
                    ->label('Default Only'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),

                EditAction::make()
                    ->icon(Heroicon::OutlinedPencil),

                ActionGroup::make([
                    Action::make('set_default')
                        ->label('Set as Default')
                        ->icon(Heroicon::OutlinedStar)
                        ->color('warning')
                        ->visible(fn (DocTemplate $record): bool => ! $record->is_default)
                        ->action(function (DocTemplate $record): void {
                            $record->setAsDefault();
                            Notification::make()
                                ->title('Template set as default')
                                ->success()
                                ->send();
                        }),

                    DeleteAction::make()
                        ->icon(Heroicon::OutlinedTrash),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->tooltip('More actions'),
            ])
            ->defaultSort('name', 'asc')
            ->striped();
    }
}
