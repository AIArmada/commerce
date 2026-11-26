<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Enrollment\EntryPoints\Filament\Resources\AffiliateOfferApplicationResource\Schemas;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Data\ApplicationStatus;
use AIArmada\AffiliateNetwork\Platform\ScopesByBelongsToOwner;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

final class AffiliateOfferApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Application Details')
                    ->schema([
                        Select::make('offer_id')
                            ->label('Offer')
                            ->relationship('offer', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutGlobalScope(ScopesByBelongsToOwner::class))
                            ->required()
                            ->disabled(),

                        Select::make('affiliate_id')
                            ->label('Affiliate')
                            ->getOptionLabelUsing(fn (mixed $value): ?string => is_string($value) ? (app(AffiliateIdentityResolver::class)->find($value)?->code ?? $value) : null)
                            ->required()
                            ->disabled(),

                        Select::make('status')
                            ->options(self::statusOptions())
                            ->required(),

                        Textarea::make('reason')
                            ->label('Application Reason')
                            ->disabled()
                            ->columnSpanFull(),

                        Textarea::make('rejection_reason')
                            ->label('Rejection/Revocation Reason')
                            ->columnSpanFull(),

                        TextInput::make('reviewed_by')
                            ->disabled(),

                        DateTimePicker::make('reviewed_at')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        $options = [];

        foreach (ApplicationStatus::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }
}
