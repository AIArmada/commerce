<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\Resources\CommunicationResource\Pages;

use AIArmada\Filament\Communications\Resources\CommunicationResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ViewCommunication extends ViewRecord
{
    protected static string $resource = CommunicationResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Details')
                    ->schema([
                        TextEntry::make('purpose'),
                        TextEntry::make('direction')->badge(),
                        TextEntry::make('category')->badge(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('priority')->badge(),
                        TextEntry::make('created_at')->dateTime(),
                    ])->columns(2),
            ]);
    }
}
