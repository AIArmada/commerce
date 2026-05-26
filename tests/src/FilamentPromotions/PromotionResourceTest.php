<?php

declare(strict_types=1);

use AIArmada\FilamentPromotions\Models\Promotion;
use AIArmada\FilamentPromotions\Resources\PromotionResource;
use AIArmada\FilamentPromotions\Resources\PromotionResource\Pages\EditPromotion;
use AIArmada\FilamentPromotions\Resources\PromotionResource\Pages\ListPromotions;
use AIArmada\FilamentPromotions\Resources\PromotionResource\Pages\ViewPromotion;
use AIArmada\FilamentPromotions\Resources\PromotionResource\RelationManagers\IssuedVouchersRelationManager;
use AIArmada\FilamentPromotions\Resources\PromotionResource\Schemas\PromotionInfolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component as LivewireComponent;

describe('PromotionResource', function (): void {
    describe('model', function (): void {
        it('uses the Filament Promotion model', function (): void {
            expect(PromotionResource::getModel())->toBe(Promotion::class);
        });
    });

    describe('navigation', function (): void {
        it('has navigation label', function (): void {
            expect(PromotionResource::getNavigationLabel())->toBe('Promotions');
        });

        it('has model label', function (): void {
            expect(PromotionResource::getModelLabel())->toBe('Promotion');
        });

        it('has plural model label', function (): void {
            expect(PromotionResource::getPluralModelLabel())->toBe('Promotions');
        });
    });

    describe('pages', function (): void {
        it('has index page', function (): void {
            $pages = PromotionResource::getPages();

            expect($pages)->toHaveKey('index');
        });

        it('has create page', function (): void {
            $pages = PromotionResource::getPages();

            expect($pages)->toHaveKey('create');
        });

        it('has view page', function (): void {
            $pages = PromotionResource::getPages();

            expect($pages)->toHaveKey('view');
        });

        it('has edit page', function (): void {
            $pages = PromotionResource::getPages();

            expect($pages)->toHaveKey('edit');
        });
    });

    describe('eloquent query', function (): void {
        it('returns query builder', function (): void {
            $query = PromotionResource::getEloquentQuery();

            expect($query)->toBeInstanceOf(Builder::class);
        });
    });

    describe('navigation badge', function (): void {
        it('returns null when no active promotions', function (): void {
            Promotion::factory()->inactive()->count(3)->create();

            expect(PromotionResource::getNavigationBadge())->toBeNull();
        });

        it('returns count when active promotions exist', function (): void {
            Promotion::factory()->active()->count(5)->create();

            expect(PromotionResource::getNavigationBadge())->toBe('5');
        });
    });

    describe('configuration', function (): void {
        it('has correct navigation group', function (): void {
            expect(PromotionResource::getNavigationGroup())->toBe(config('filament-promotions.navigation_group'));
        });

        it('has navigation badge color', function (): void {
            expect(PromotionResource::getNavigationBadgeColor())->toBe('success');
        });

        it('registers issued vouchers relation manager when voucher tracking is available', function (): void {
            $relations = PromotionResource::getRelations();

            if (Promotion::supportsIssuedVoucherTracking()) {
                expect($relations)->toContain(IssuedVouchersRelationManager::class);

                return;
            }

            expect($relations)->toBe([]);
        });

        it('adds issue vouchers header action on view and edit pages when voucher tracking is available', function (): void {
            $viewPage = app(ViewPromotion::class);
            $editPage = app(EditPromotion::class);
            $viewMethod = new ReflectionMethod(ViewPromotion::class, 'getHeaderActions');
            $editMethod = new ReflectionMethod(EditPromotion::class, 'getHeaderActions');
            $viewActionNames = collect($viewMethod->invoke($viewPage))
                ->map(fn (object $action): string => $action->getName())
                ->values()
                ->all();
            $editActionNames = collect($editMethod->invoke($editPage))
                ->map(fn (object $action): string => $action->getName())
                ->values()
                ->all();

            if (Promotion::supportsIssuedVoucherTracking()) {
                expect($viewActionNames)->toContain('issue_vouchers');
                expect($editActionNames)->toContain('issue_vouchers');

                return;
            }

            expect($viewActionNames)->not()->toContain('issue_vouchers');
            expect($editActionNames)->not()->toContain('issue_vouchers');
        });

        it('adds issue vouchers header action on the list page when voucher tracking is available', function (): void {
            $listPage = app(ListPromotions::class);
            $method = new ReflectionMethod(ListPromotions::class, 'getHeaderActions');
            $actionNames = collect($method->invoke($listPage))
                ->map(fn (object $action): string => $action->getName())
                ->values()
                ->all();

            expect($actionNames)->toContain('create');

            if (Promotion::supportsIssuedVoucherTracking()) {
                expect($actionNames)->toContain('issue_vouchers');

                return;
            }

            expect($actionNames)->not()->toContain('issue_vouchers');
        });

        it('builds issued vouchers relation manager table definition', function (): void {
            $manager = new IssuedVouchersRelationManager;

            $livewire = Mockery::mock(HasTable::class);
            $table = $manager->table(Table::make($livewire));
            $columnNames = collect($table->getColumns())
                ->map(fn (object $column): string => $column->getName())
                ->values()
                ->all();

            expect($columnNames)
                ->toContain('code')
                ->toContain('status')
                ->toContain('usages_count');
        });

        it('adds issue vouchers as a promotions table row action when voucher tracking is available', function (): void {
            $livewire = Mockery::mock(HasTable::class);
            $table = PromotionResource::table(Table::make($livewire));

            if (Promotion::supportsIssuedVoucherTracking()) {
                expect($table->getAction('issue_vouchers'))->not()->toBeNull();

                return;
            }

            expect($table->getAction('issue_vouchers'))->toBeNull();
        });

        it('scopes queries via getEloquentQuery rather than Filament tenancy', function (): void {
            $query = PromotionResource::getEloquentQuery();

            expect($query)->toBeInstanceOf(Builder::class);

            $reflection = new ReflectionClass(PromotionResource::class);
            $property = $reflection->getProperty('tenantOwnershipRelationshipName');

            expect($property->getValue())->toBeNull();
        });

        it('uses persisted promotion attributes in infolist text entries', function (): void {
            $livewire = new class extends LivewireComponent implements HasSchemas
            {
                use InteractsWithSchemas;

                public function render(): string
                {
                    return '';
                }
            };

            $schema = PromotionInfolist::configure(Schema::make($livewire));

            $flatten = function (array $components) use (&$flatten): array {
                $all = [];

                foreach ($components as $component) {
                    if (! is_object($component)) {
                        continue;
                    }

                    $all[] = $component;

                    if (method_exists($component, 'getChildComponents')) {
                        $all = [...$all, ...$flatten($component->getChildComponents())];
                    }
                }

                return $all;
            };

            $entryNames = collect($flatten($schema->getComponents()))
                ->filter(fn (object $component): bool => $component instanceof TextEntry)
                ->map(fn (TextEntry $entry): string => $entry->getName())
                ->values()
                ->all();

            expect($entryNames)
                ->toContain('min_purchase_amount')
                ->toContain('min_quantity')
                ->toContain('per_customer_limit')
                ->toContain('issued_vouchers_count')
                ->toContain('redeemed_issued_vouchers_count')
                ->toContain('active_issued_vouchers_count')
                ->not->toContain('min_order_value')
                ->not->toContain('max_discount')
                ->not->toContain('usage_per_customer');
        });
    });
});
