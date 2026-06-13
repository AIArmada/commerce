<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Pages;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCustomers\Actions\MergeCustomersAction;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
final class MergeCustomersPage extends Page implements HasForms
{
    use InteractsWithForms;

    public ?string $targetCustomerId = null;

    public ?string $sourceCustomerId = null;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    /** @var view-string */
    protected string $view = 'filament-customers::pages.merge-customers';

    protected static ?string $navigationLabel = 'Merge Customers';

    protected static ?string $title = 'Merge Customers';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-customers.navigation.group');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-customers.features.merge_customers', true);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('targetCustomerId')
                    ->label('Target Customer (keep this one)')
                    ->placeholder('Search for the customer to keep...')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => $this->searchCustomers($search))
                    ->getOptionLabelUsing(fn (string $value): string => $this->getCustomerLabel($value))
                    ->required(),
                Select::make('sourceCustomerId')
                    ->label('Source Customer (merge from this one)')
                    ->placeholder('Search for the customer to merge from...')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => $this->searchCustomers($search))
                    ->getOptionLabelUsing(fn (string $value): string => $this->getCustomerLabel($value))
                    ->required()
                    ->rules([
                        fn (callable $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            if ($value !== null && $value === $get('targetCustomerId')) {
                                $fail('Target and source customer must be different.');
                            }
                        },
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * @return array<string, string>
     */
    protected function searchCustomers(string $search): array
    {
        return Customer::query()
            ->tap(fn ($query) => OwnerUiScope::apply($query))
            ->where(function ($query) use ($search): void {
                $query->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%");
            })
            ->limit(20)
            ->get()
            ->mapWithKeys(fn (Customer $customer): array => [
                $customer->id => $this->getCustomerLabel($customer->id),
            ])
            ->all();
    }

    protected function getCustomerLabel(string $id): string
    {
        $customer = $this->resolveCustomer($id);

        if (! $customer) {
            return '';
        }

        $parts = array_filter([
            $customer->first_name,
            $customer->last_name,
            $customer->email,
            $customer->company,
        ]);

        return implode(' - ', $parts);
    }

    public function merge(): void
    {
        $data = $this->form->getState();

        $targetId = $data['targetCustomerId'] ?? null;
        $sourceId = $data['sourceCustomerId'] ?? null;

        if (! is_string($targetId) || ! is_string($sourceId) || $targetId === '' || $sourceId === '') {
            return;
        }

        $target = $this->resolveCustomer($targetId);
        $source = $this->resolveCustomer($sourceId);

        if (! $target || ! $source) {
            Notification::make()
                ->danger()
                ->title('Customer not found')
                ->send();

            return;
        }

        app(MergeCustomersAction::class)->execute($target, $source);

        Notification::make()
            ->success()
            ->title('Customers merged successfully')
            ->body("{$source->email} has been merged into {$target->email}")
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('merge')
                ->label('Merge Customers')
                ->action('merge')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Confirm Customer Merge')
                ->modalDescription('This action will merge all data from the source customer into the target customer and delete the source. This cannot be undone.')
                ->modalSubmitActionLabel('Yes, merge them'),
        ];
    }

    private function resolveCustomer(string $id): ?Customer
    {
        if (! (bool) config('customers.features.owner.enabled', false)) {
            return Customer::find($id);
        }

        /** @var Customer $customer */
        $customer = OwnerWriteGuard::findOrFailForOwner(Customer::class, $id, includeGlobal: false);

        return $customer;
    }
}
