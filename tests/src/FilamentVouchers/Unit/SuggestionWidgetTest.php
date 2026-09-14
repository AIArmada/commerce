<?php

declare(strict_types=1);

use AIArmada\Cart\Snapshots\CartInstanceManager;
use AIArmada\Cart\Snapshots\CartSnapshot as Cart;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Widgets\VoucherSuggestionsWidget;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\States\Active;

uses(TestCase::class);

function suggestionCart(): Cart
{
    config()->set('vouchers.owner.enabled', false);

    return Cart::query()->create([
        'instance' => 'default',
        'identifier' => 'suggest-' . mb_strtolower(Str::random(6)),
        'currency' => 'USD',
        'subtotal' => 10000,
        'total' => 10000,
    ]);
}

function suggestionVoucher(array $overrides = []): Voucher
{
    return Voucher::query()->create(array_merge([
        'code' => 'SUG-' . mb_strtoupper(Str::random(6)),
        'name' => 'Suggestion',
        'type' => VoucherType::Fixed,
        'value' => 500,
        'currency' => 'USD',
        'status' => Active::class,
        'starts_at' => now()->subDay(),
    ], $overrides));
}

it('applies suggestions by record key, resolving the code server-side', function (): void {
    $cart = suggestionCart();
    $voucher = suggestionVoucher();

    $applied = [];
    app()->instance(CartInstanceManager::class, new class($applied)
    {
        /** @var array<int, string> */
        public array $applied;

        public function __construct(array &$applied)
        {
            $this->applied = &$applied;
        }

        public function resolve(string $instance, string $identifier): object
        {
            $applied = &$this->applied;

            return new class($applied)
            {
                /** @var array<int, string> */
                public array $applied;

                public function __construct(array &$applied)
                {
                    $this->applied = &$applied;
                }

                public function getAppliedVouchers(): array
                {
                    return [];
                }

                public function applyVoucher(string $code): void
                {
                    $this->applied[] = $code;
                }
            };
        }
    });

    $widget = app(VoucherSuggestionsWidget::class);
    $widget->record = $cart;
    $widget->applySuggestion((string) $voucher->getKey());

    // A hostile code string can no longer ride the action argument: unknown
    // keys resolve to nothing and apply nothing.
    $widget->applySuggestion("x'); DROP TABLE vouchers; --");

    expect($applied)->toBe([$voucher->code]);
});

it('resolves the cart once no matter how many candidates exist', function (): void {
    $cart = suggestionCart();
    suggestionVoucher();
    suggestionVoucher();
    suggestionVoucher();

    $resolutions = 0;
    app()->instance(CartInstanceManager::class, new class($resolutions)
    {
        public int $resolutions;

        public function __construct(int &$resolutions)
        {
            $this->resolutions = &$resolutions;
        }

        public function resolve(string $instance, string $identifier): object
        {
            $this->resolutions++;

            return new class
            {
                public function getAppliedVouchers(): array
                {
                    return [];
                }
            };
        }
    });

    $widget = app(VoucherSuggestionsWidget::class);
    $widget->record = $cart;

    expect($widget->getEligibleVouchers())->not->toBeEmpty()
        ->and($resolutions)->toBe(1);
});
