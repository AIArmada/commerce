<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources;

use AIArmada\Chip\Models\SendInstruction;
use AIArmada\FilamentChip\Resources\SendInstructionResource\Pages\CreateSendInstruction;
use AIArmada\FilamentChip\Resources\SendInstructionResource\Pages\ListSendInstructions;
use AIArmada\FilamentChip\Resources\SendInstructionResource\Pages\ViewSendInstruction;
use AIArmada\FilamentChip\Resources\SendInstructionResource\Schemas\SendInstructionForm;
use AIArmada\FilamentChip\Resources\SendInstructionResource\Schemas\SendInstructionInfolist;
use AIArmada\FilamentChip\Resources\SendInstructionResource\Tables\SendInstructionTable;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;

final class SendInstructionResource extends BaseChipResource
{
    protected static ?string $model = SendInstruction::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'Payout';

    protected static ?string $pluralModelLabel = 'Payouts';

    protected static ?string $recordTitleAttribute = 'reference';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return SendInstructionForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return SendInstructionTable::configure($table);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return SendInstructionInfolist::configure($schema);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'reference',
            'email',
            'description',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListSendInstructions::route('/'),
            'create' => CreateSendInstruction::route('/create'),
            'view' => ViewSendInstruction::route('/{record}'),
        ];
    }

    protected static function navigationSortKey(): string
    {
        return 'send_instructions';
    }
}
