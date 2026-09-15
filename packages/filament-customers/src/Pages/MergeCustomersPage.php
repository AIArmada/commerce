<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Pages;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\LikeSearch;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCustomers\Actions\MergeCustomersAction;
use AIArmada\FilamentCustomers\Support\PrimaryEmailResolver;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
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

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-customers.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-customers.pages.navigation_sort.merge_customers');

        return is_numeric($sort) ? (int) $sort : null;
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
                    ->getOptionLabelUsing(fn (?string $value): string => $value !== null ? $this->getCustomerLabel($value) : '')
                    ->required(),
                Select::make('sourceCustomerId')
                    ->label('Source Customer (merge from this one)')
                    ->placeholder('Search for the customer to merge from...')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => $this->searchCustomers($search))
                    ->getOptionLabelUsing(fn (?string $value): string => $value !== null ? $this->getCustomerLabel($value) : '')
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
        $query = Customer::query()
            ->tap(fn ($query) => OwnerUiScope::apply($query))
            ->with('contactMethods');

        $pattern = LikeSearch::contains($search);

        return $query
            ->where(function ($query) use ($pattern): void {
                LikeSearch::whereLike($query, 'first_name', $pattern);
                LikeSearch::orWhereLike($query, 'last_name', $pattern);
                LikeSearch::orWhereLike($query, 'company', $pattern);
                $query->orWhereHas('contactMethods', function ($contactMethods) use ($pattern): void {
                    $table = $contactMethods->getModel()->getTable();

                    $contactMethods
                        ->whereIn('type', ['email', 'phone', 'mobile', 'whatsapp'])
                        ->where(function ($contactMethods) use ($pattern, $table): void {
                            LikeSearch::whereLike($contactMethods, "{$table}.value", $pattern);
                            LikeSearch::orWhereLike($contactMethods, "{$table}.normalized_value", $pattern);
                        });
                });
            })
            ->limit(20)
            ->get()
            ->mapWithKeys(fn (Customer $customer): array => [
                $customer->id => self::labelForCustomer($customer),
            ])
            ->all();
    }

    protected function getCustomerLabel(string $id): string
    {
        $customer = $this->resolveCustomer($id);

        if (! $customer) {
            return '';
        }

        return self::labelForCustomer($customer);
    }

    private static function labelForCustomer(Customer $customer): string
    {
        $parts = array_filter([
            $customer->first_name,
            $customer->last_name,
            PrimaryEmailResolver::resolve($customer),
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

        $this->mergeCustomers($targetId, $sourceId);
    }

    protected function mergeCustomers(string $targetId, string $sourceId): void
    {
        if ($targetId === $sourceId) {
            Notification::make()
                ->danger()
                ->title('Target and source customer must be different.')
                ->send();

            return;
        }

        try {
            $target = $this->resolveCustomer($targetId);
            $source = $this->resolveCustomer($sourceId);
        } catch (AuthorizationException) {
            Notification::make()
                ->danger()
                ->title('Customer not found')
                ->send();

            return;
        }

        if (! $target || ! $source) {
            Notification::make()
                ->danger()
                ->title('Customer not found')
                ->send();

            return;
        }

        $user = Filament::auth()->user();
        abort_unless($user !== null, 403);

        Gate::forUser($user)->authorize('update', $target);
        Gate::forUser($user)->authorize('update', $source);
        Gate::forUser($user)->authorize('delete', $source);

        try {
            app(MergeCustomersAction::class)->execute($target, $source);
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->danger()
                ->title('Customers could not be merged')
                ->body($exception->getMessage())
                ->send();

            return;
        }

        $sourceLabel = PrimaryEmailResolver::resolve($source) ?? $source->full_name;
        $targetLabel = PrimaryEmailResolver::resolve($target) ?? $target->full_name;

        Notification::make()
            ->success()
            ->title('Customers merged successfully')
            ->body("{$sourceLabel} has been merged into {$targetLabel}")
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
