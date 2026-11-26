<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Ledger\EntryPoints\Filament\Resources\MerchantStatementResource\Pages;

use AIArmada\AffiliateNetwork\Features\Ledger\Actions\GenerateMerchantStatement;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliateNetwork\Features\Ledger\EntryPoints\Filament\Resources\MerchantStatementResource;
use Carbon\CarbonImmutable;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

final class ListMerchantStatements extends ListRecords
{
    protected static string $resource = MerchantStatementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate')
                ->label('Generate')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->form([
                    Select::make('site_id')
                        ->label('Site')
                        ->options(fn (): array => AffiliateSite::query()
                            ->withoutOwnerScope()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->required(),
                    TextInput::make('currency')
                        ->label('Currency')
                        ->length(3)
                        ->alpha()
                        ->required(),
                    DatePicker::make('period_start')
                        ->label('Period start')
                        ->required(),
                    DatePicker::make('period_end')
                        ->label('Period end')
                        ->required()
                        ->afterOrEqual('period_start'),
                ])
                ->action(function (array $data): void {
                    $site = AffiliateSite::query()
                        ->withoutOwnerScope()
                        ->whereKey((string) $data['site_id'])
                        ->firstOrFail();

                    $owner = OwnerContext::fromTypeAndId($site->owner_type, $site->owner_id);

                    $statement = OwnerContext::withOwner($owner, fn () => app(GenerateMerchantStatement::class)->execute(
                        (string) $site->getKey(),
                        (string) $data['currency'],
                        CarbonImmutable::parse((string) $data['period_start'])->startOfDay(),
                        CarbonImmutable::parse((string) $data['period_end'])->endOfDay(),
                    ));

                    Notification::make()
                        ->title('Statement generated')
                        ->body((string) $statement->statement_number)
                        ->success()
                        ->send();
                }),
        ];
    }
}
