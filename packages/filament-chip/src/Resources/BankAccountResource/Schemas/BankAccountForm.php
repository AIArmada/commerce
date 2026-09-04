<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\BankAccountResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class BankAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Details')
                    ->description('Bank account information for receiving payouts.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Account Holder Name')
                            ->required()
                            ->helperText('The full name of the account holder as it appears on the bank account.'),

                        TextInput::make('account_number')
                            ->label('Account Number')
                            ->required()
                            ->helperText('The bank account number.'),

                        Select::make('bank_code')
                            ->label('Bank code')
                            ->options(self::getSupportedBankCodes())
                            ->searchable()
                            ->required()
                            ->helperText('Select the bank code accepted by CHIP Send.'),
                    ]),

                Section::make('Configuration')
                    ->description('Reference information for this recipient account.')
                    ->schema([
                        TextInput::make('reference')
                            ->label('Reference')
                            ->required()
                            ->helperText('A unique reference used to prevent duplicate submissions.'),
                    ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function getSupportedBankCodes(): array
    {
        return [
            'ACDBMYK2' => 'AEON Bank (M) Berhad',
            'PHBMMYKL' => 'Affin Bank Berhad',
            'AGOBMYKL' => 'Agrobank',
            'RJHIMYKL' => 'Al-Rajhi',
            'MFBBMYKL' => 'Alliance Bank Malaysia Berhad',
            'ARBKMYKL' => 'Ambank Malaysia Berhad',
            'BIMBMYKL' => 'Bank Islam Malaysia Berhad',
            'BKRMMYKL' => 'Bank Kerjasama Rakyat Malaysia Berhad',
            'BMMBMYKL' => 'Bank Muamalat Malaysia Bhd',
            'BOFAMY2X' => 'Bank of America (M) Berhad',
            'BKCHMYKL' => 'Bank of China (M) Berhad',
            'BOTKMYKX' => 'Bank of Tokyo-Mitsubishi UFJ (M) Berhad',
            'BSNAMYK1' => 'Bank Simpanan Nasional Berhad',
            'BNPAMYKL' => 'BNP Paribas Malaysia Berhad',
            'PCBCMYKL' => 'China Construction Bank (M) Berhad',
            'CIBBMYKL' => 'CIMB Bank Berhad',
            'DEUTMYKL' => 'Deutsche Bank (Malaysia) Berhad',
            'FNXSMYNB' => 'Finexus Cards Sdn. Bhd.',
            'GXSPMYKL' => 'GX Bank Berhad',
            'HLBBMYKL' => 'Hong Leong Bank Berhad',
            'HBMBMYKL' => 'HSBC Bank Malaysia Berhad',
            'ICBKMYKL' => 'Industrial and Commercial Bank of China (M) Berhad',
            'CHASMYKX' => 'JP Morgan Chase Bank Berhad',
            'KFHOMYKL' => 'Kuwait Finance House',
            'MBBEMYKL' => 'Maybank',
            'AFBQMYKL' => 'MBSB Bank Berhad',
            'MHCBMYKA' => 'Mizuho Bank (Malaysia) Berhad',
            'OCBCMYKL' => 'OCBC Malaysia',
            'PBBEMYKL' => 'Public Bank Berhad',
            'RHBBMYKL' => 'RHB Bank Berhad',
            'SCBLMYKX' => 'Standard Chartered Malaysia',
            'SMBCMYKL' => 'Sumitomo Mitsui Banking Corporation (M) Berhad',
            'TNGDMYNB' => "Touch 'n Go eWallet",
            'UOVBMYKL' => 'United Overseas Bank Berhad (UOB)',
        ];
    }
}
