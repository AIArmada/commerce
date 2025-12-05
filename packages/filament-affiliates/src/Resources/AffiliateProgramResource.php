<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources;

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\AffiliateProgram;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class AffiliateProgramResource extends Resource
{
    protected static ?string $model = AffiliateProgram::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Affiliates';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Program Details')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    Forms\Components\Textarea::make('description')
                        ->rows(3),

                    Forms\Components\Select::make('status')
                        ->options(ProgramStatus::class)
                        ->required()
                        ->default(ProgramStatus::Draft),
                ])
                ->columns(2),

            Forms\Components\Section::make('Schedule')
                ->schema([
                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label('Start Date'),

                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label('End Date')
                        ->after('starts_at'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Commission Settings')
                ->schema([
                    Forms\Components\Select::make('commission_type')
                        ->options(CommissionType::class)
                        ->required()
                        ->default(CommissionType::Percentage),

                    Forms\Components\TextInput::make('default_commission_rate_basis_points')
                        ->label('Default Commission Rate (basis points)')
                        ->numeric()
                        ->required()
                        ->default(1000)
                        ->helperText('1000 = 10%'),

                    Forms\Components\TextInput::make('cookie_lifetime_days')
                        ->label('Cookie Lifetime (days)')
                        ->numeric()
                        ->required()
                        ->default(30),
                ])
                ->columns(3),

            Forms\Components\Section::make('Settings')
                ->schema([
                    Forms\Components\Toggle::make('is_public')
                        ->label('Public Program')
                        ->default(true)
                        ->helperText('Visible to all affiliates'),

                    Forms\Components\Toggle::make('requires_approval')
                        ->label('Requires Approval')
                        ->default(true)
                        ->helperText('New members require admin approval'),

                    Forms\Components\TextInput::make('terms_url')
                        ->label('Terms & Conditions URL')
                        ->url(),
                ])
                ->columns(3),

            Forms\Components\Section::make('Eligibility Rules')
                ->schema([
                    Forms\Components\KeyValue::make('eligibility_rules')
                        ->keyLabel('Requirement')
                        ->valueLabel('Value')
                        ->addActionLabel('Add Requirement'),
                ])
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => ProgramStatus::Draft->value,
                        'success' => ProgramStatus::Active->value,
                        'warning' => ProgramStatus::Paused->value,
                        'danger' => ProgramStatus::Archived->value,
                    ]),

                Tables\Columns\IconColumn::make('is_public')
                    ->boolean()
                    ->label('Public'),

                Tables\Columns\TextColumn::make('default_commission_rate_basis_points')
                    ->label('Commission')
                    ->formatStateUsing(fn ($state) => ($state / 100).'%'),

                Tables\Columns\TextColumn::make('affiliates_count')
                    ->counts('affiliates')
                    ->label('Members'),

                Tables\Columns\TextColumn::make('starts_at')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('ends_at')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(ProgramStatus::class),

                Tables\Filters\TernaryFilter::make('is_public')
                    ->label('Public'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => AffiliateProgramResource\Pages\ListAffiliatePrograms::route('/'),
            'create' => AffiliateProgramResource\Pages\CreateAffiliateProgram::route('/create'),
            'view' => AffiliateProgramResource\Pages\ViewAffiliateProgram::route('/{record}'),
            'edit' => AffiliateProgramResource\Pages\EditAffiliateProgram::route('/{record}/edit'),
        ];
    }
}
