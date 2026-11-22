<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Schemas;

use AIArmada\Contacting\Enums\ContactMethodType;
use AIArmada\Contacting\Enums\ContactPurpose;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use Ysfkaya\FilamentPhoneInput\PhoneInputNumberType;

final class ContactMethodRepeaterSchema
{
    public static function make(
        string $name = 'contactMethods',
        ?string $helperText = null,
        string $initialCountry = 'MY',
    ): Repeater {
        $typeOptions = [];

        foreach (ContactMethodType::options() as $key => $label) {
            $typeOptions[$key] = __($label);
        }

        $purposeOptions = [];

        foreach (ContactPurpose::options() as $key => $label) {
            $purposeOptions[$key] = __($label);
        }

        $repeater = Repeater::make($name)
            ->label(__('Contact Details'))
            ->default([])
            ->schema([
                Select::make('type')
                    ->label(__('Type'))
                    ->options($typeOptions)
                    ->required()
                    ->live(),
                ...self::valueFields($initialCountry),
                Select::make('purpose')
                    ->label(__('Purpose'))
                    ->options($purposeOptions)
                    ->default(ContactPurpose::General->value)
                    ->required(),
                Grid::make(2)->schema([
                    Toggle::make('is_primary')
                        ->label(__('Primary'))
                        ->fixIndistinctState(),
                    Toggle::make('is_public')
                        ->label(__('Public'))
                        ->default(true),
                ])->columnSpanFull(),
            ])
            ->columns(4)
            ->addActionLabel(__('Add contact details'))
            ->mutateRelationshipDataBeforeFillUsing(static fn (array $data): array => self::normalizeRowsForFill($data))
            ->mutateRelationshipDataBeforeCreateUsing(static fn (array $data): array => self::normalizeRowsForSave($data))
            ->mutateRelationshipDataBeforeSaveUsing(static fn (array $data): array => self::normalizeRowsForSave($data));

        if ($helperText !== null) {
            $repeater->helperText(__($helperText));
        }

        return $repeater;
    }

    /**
     * @return array<int, TextInput|PhoneInput>
     */
    public static function valueFields(string $initialCountry = 'MY'): array
    {
        $phoneTypes = [
            ContactMethodType::Phone,
            ContactMethodType::Phone->value,
            ContactMethodType::Whatsapp,
            ContactMethodType::Whatsapp->value,
            ContactMethodType::Mobile,
            ContactMethodType::Mobile->value,
        ];
        $emailTypes = [ContactMethodType::Email, ContactMethodType::Email->value];

        $phoneLabel = static fn (Get $get): string => match ($get('type')) {
            ContactMethodType::Phone, ContactMethodType::Phone->value => __('Phone Number'),
            ContactMethodType::Whatsapp, ContactMethodType::Whatsapp->value => __('WhatsApp Number'),
            ContactMethodType::Mobile, ContactMethodType::Mobile->value => __('Mobile Number'),
            default => __('Value'),
        };

        if (class_exists(PhoneInput::class)) {
            $phoneField = PhoneInput::make('phone_value')
                ->label($phoneLabel)
                ->required()
                ->visible(static fn (Get $get): bool => in_array($get('type'), $phoneTypes, true))
                ->dehydrated(static fn (Get $get): bool => in_array($get('type'), $phoneTypes, true))
                ->afterStateHydrated(static function (PhoneInput $component, mixed $state, Get $get, Set $set): void {
                    if (! self::isPhoneContactType($get('type'))) {
                        return;
                    }

                    $value = self::normalizedContactValue($state) ?? self::normalizedContactValue($get('value'));

                    if ($value === null) {
                        return;
                    }

                    $component->state($value);
                    $set('value', $value);
                })
                ->afterStateUpdated(static function (Set $set, mixed $state): void {
                    $set('value', self::normalizedContactValue($state));
                })
                ->initialCountry($initialCountry);

            if (class_exists(PhoneInputNumberType::class)) {
                $phoneField
                    ->displayNumberFormat(PhoneInputNumberType::INTERNATIONAL)
                    ->inputNumberFormat(PhoneInputNumberType::E164);
            }

            return [
                $phoneField,
                TextInput::make('value')
                    ->label(static fn (Get $get): string => match ($get('type')) {
                        ContactMethodType::Email, ContactMethodType::Email->value => __('Email Address'),
                        default => __('Value'),
                    })
                    ->required()
                    ->maxLength(255)
                    ->visible(static fn (Get $get): bool => ! in_array($get('type'), $phoneTypes, true))
                    ->dehydrated(static fn (Get $get): bool => ! in_array($get('type'), $phoneTypes, true))
                    ->email(static fn (Get $get): bool => in_array($get('type'), $emailTypes, true)),
            ];
        }

        return [
            TextInput::make('value')
                ->label(static fn (Get $get): string => in_array($get('type'), $phoneTypes, true)
                    ? $phoneLabel($get)
                    : match ($get('type')) {
                        ContactMethodType::Email, ContactMethodType::Email->value => __('Email Address'),
                        default => __('Value'),
                    })
                ->required()
                ->maxLength(255)
                ->tel(static fn (Get $get): bool => in_array($get('type'), $phoneTypes, true))
                ->email(static fn (Get $get): bool => in_array($get('type'), $emailTypes, true)),
        ];
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>  $data
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    public static function normalizeRowsForFill(array $data): array
    {
        if (array_is_list($data)) {
            return array_map(self::normalizeRowForFill(...), $data);
        }

        return self::normalizeRowForFill($data);
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>  $data
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    public static function normalizeRowsForSave(array $data): array
    {
        if (array_is_list($data)) {
            return array_map(self::normalizeRowForSave(...), $data);
        }

        return self::normalizeRowForSave($data);
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>  $data
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    public static function normalizeRowsForComparison(array $data): array
    {
        if (array_is_list($data)) {
            return array_map(self::normalizeRowForComparison(...), $data);
        }

        return self::normalizeRowForComparison($data);
    }

    public static function isPhoneContactType(mixed $type): bool
    {
        $typeValue = $type instanceof ContactMethodType
            ? $type->value
            : mb_strtolower((string) $type);

        return in_array($typeValue, [
            ContactMethodType::Phone->value,
            ContactMethodType::Whatsapp->value,
            ContactMethodType::Mobile->value,
        ], true);
    }

    public static function normalizedContactValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = mb_trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    public static function normalizedComparablePhoneValue(mixed $value): ?string
    {
        $normalized = self::normalizedContactValue($value);

        if ($normalized === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $normalized);

        if (! is_string($digits) || $digits === '') {
            return null;
        }

        return str_starts_with($normalized, '+') ? '+' . $digits : $digits;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function itemLabel(array $state): string
    {
        $type = $state['type'] ?? $state['category'] ?? null;

        if ($type instanceof ContactMethodType) {
            $typeLabel = $type->label();
        } elseif (is_string($type)) {
            $typeLabel = ContactMethodType::tryFrom($type)?->label() ?? $type;
        } else {
            $typeLabel = __('Contact');
        }

        $value = self::isPhoneContactType($type)
            ? self::normalizedContactValue($state['phone_value'] ?? ($state['value'] ?? null))
            : self::normalizedContactValue($state['value'] ?? null);

        return $typeLabel . ': ' . ($value ?? '');
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    private static function normalizeRowForFill(array $contact): array
    {
        $contact['type'] ??= $contact['category'] ?? null;
        $contact['purpose'] ??= ContactPurpose::General->value;

        unset($contact['category']);

        if (self::isPhoneContactType($contact['type'] ?? null)) {
            $contact['phone_value'] = $contact['value'] ?? null;
        }

        return $contact;
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    private static function normalizeRowForSave(array $contact): array
    {
        $contact['type'] ??= $contact['category'] ?? null;
        $contact['purpose'] ??= ContactPurpose::General->value;
        $type = $contact['type'] ?? null;

        unset($contact['category']);

        if (self::isPhoneContactType($type)) {
            $value = self::normalizedContactValue($contact['phone_value'] ?? $contact['value'] ?? null);

            if ($value !== null) {
                $contact['value'] = $value;
            }

            unset($contact['phone_value']);

            return $contact;
        }

        $value = self::normalizedContactValue($contact['value'] ?? null);

        if ($value !== null) {
            $contact['value'] = $value;
        }

        unset($contact['phone_value']);

        return $contact;
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    private static function normalizeRowForComparison(array $contact): array
    {
        $contact = self::normalizeRowForSave($contact);
        $contact = self::normalizeRowForFill($contact);

        if (! self::isPhoneContactType($contact['type'] ?? null)) {
            return $contact;
        }

        $value = self::normalizedComparablePhoneValue($contact['phone_value'] ?? ($contact['value'] ?? null));

        if ($value !== null) {
            $contact['phone_value'] = $value;
        }

        unset($contact['value']);

        return $contact;
    }
}
