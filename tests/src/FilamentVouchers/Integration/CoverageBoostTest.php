<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Actions\ActivateCampaignAction;
use AIArmada\FilamentVouchers\Actions\ActivateGiftCardAction;
use AIArmada\FilamentVouchers\Actions\ActivateVoucherAction;
use AIArmada\FilamentVouchers\Actions\AddToMyWalletAction;
use AIArmada\FilamentVouchers\Actions\ApplyVoucherToCartAction;
use AIArmada\FilamentVouchers\Actions\BulkGenerateVouchersAction;
use AIArmada\FilamentVouchers\Actions\BulkIssueGiftCardsAction;
use AIArmada\FilamentVouchers\Actions\DeclareABWinnerAction;
use AIArmada\FilamentVouchers\Actions\ManualRedeemVoucherAction;
use AIArmada\FilamentVouchers\Actions\MarkFraudReviewedAction;
use AIArmada\FilamentVouchers\Actions\PauseCampaignAction;
use AIArmada\FilamentVouchers\Actions\PauseVoucherAction;
use AIArmada\FilamentVouchers\Actions\SuspendGiftCardAction;
use AIArmada\FilamentVouchers\Actions\TopUpGiftCardAction;
use AIArmada\FilamentVouchers\Extensions\CartVoucherActions;
use AIArmada\FilamentVouchers\FilamentVouchersPlugin;
use AIArmada\FilamentVouchers\Pages\ABTestDashboard;
use AIArmada\FilamentVouchers\Pages\FraudConfigurationPage;
use AIArmada\FilamentVouchers\Pages\StackingConfigurationPage;
use AIArmada\FilamentVouchers\Pages\TargetingConfigurationPage;
use AIArmada\FilamentVouchers\Resources\CampaignResource;
use AIArmada\FilamentVouchers\Resources\CampaignResource\Pages\EditCampaign;
use AIArmada\FilamentVouchers\Resources\CampaignResource\Pages\ListCampaigns;
use AIArmada\FilamentVouchers\Resources\CampaignResource\Pages\ViewCampaign;
use AIArmada\FilamentVouchers\Resources\CampaignResource\RelationManagers\VariantsRelationManager;
use AIArmada\FilamentVouchers\Resources\CampaignResource\RelationManagers\VouchersRelationManager;
use AIArmada\FilamentVouchers\Resources\CampaignResource\Schemas\CampaignForm;
use AIArmada\FilamentVouchers\Resources\CampaignResource\Schemas\CampaignInfolist;
use AIArmada\FilamentVouchers\Resources\CampaignResource\Tables\CampaignsTable;
use AIArmada\FilamentVouchers\Resources\FraudSignalResource;
use AIArmada\FilamentVouchers\Resources\FraudSignalResource\Pages\ListFraudSignals;
use AIArmada\FilamentVouchers\Resources\FraudSignalResource\Pages\ViewFraudSignal;
use AIArmada\FilamentVouchers\Resources\FraudSignalResource\Schemas\FraudSignalInfolist;
use AIArmada\FilamentVouchers\Resources\FraudSignalResource\Tables\FraudSignalsTable;
use AIArmada\FilamentVouchers\Resources\GiftCardResource;
use AIArmada\FilamentVouchers\Resources\GiftCardResource\Pages\CreateGiftCard;
use AIArmada\FilamentVouchers\Resources\GiftCardResource\Pages\EditGiftCard;
use AIArmada\FilamentVouchers\Resources\GiftCardResource\Pages\ListGiftCards;
use AIArmada\FilamentVouchers\Resources\GiftCardResource\Pages\ViewGiftCard;
use AIArmada\FilamentVouchers\Resources\GiftCardResource\RelationManagers\TransactionsRelationManager;
use AIArmada\FilamentVouchers\Resources\GiftCardResource\Schemas\GiftCardForm;
use AIArmada\FilamentVouchers\Resources\GiftCardResource\Schemas\GiftCardInfolist;
use AIArmada\FilamentVouchers\Resources\GiftCardResource\Tables\GiftCardsTable;
use AIArmada\FilamentVouchers\Resources\VoucherResource;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Pages\CreateVoucher;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Pages\EditVoucher;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Pages\ListVouchers;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Pages\ViewVoucher;
use AIArmada\FilamentVouchers\Resources\VoucherResource\RelationManagers\VoucherUsagesRelationManager;
use AIArmada\FilamentVouchers\Resources\VoucherResource\RelationManagers\WalletEntriesRelationManager;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Schemas\VoucherForm;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Schemas\VoucherInfolist;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Tables\VouchersTable;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Tables\WalletEntriesTable;
use AIArmada\FilamentVouchers\Resources\VoucherUsageResource;
use AIArmada\FilamentVouchers\Resources\VoucherUsageResource\Pages\ListVoucherUsages;
use AIArmada\FilamentVouchers\Resources\VoucherUsageResource\Tables\VoucherUsagesTable;
use AIArmada\FilamentVouchers\Resources\VoucherWalletResource;
use AIArmada\FilamentVouchers\Resources\VoucherWalletResource\Tables\VoucherWalletsTable;
use AIArmada\FilamentVouchers\Widgets\AIInsightsWidget;
use AIArmada\FilamentVouchers\Widgets\AppliedVoucherBadgesWidget;
use AIArmada\FilamentVouchers\Widgets\AppliedVouchersWidget;
use AIArmada\FilamentVouchers\Widgets\CampaignStatsWidget;
use AIArmada\FilamentVouchers\Widgets\FraudStatsWidget;
use AIArmada\FilamentVouchers\Widgets\GiftCardStatsWidget;
use AIArmada\FilamentVouchers\Widgets\GiftCardTransactionTimelineWidget;
use AIArmada\FilamentVouchers\Widgets\QuickApplyVoucherWidget;
use AIArmada\FilamentVouchers\Widgets\RedemptionTrendChart;
use AIArmada\FilamentVouchers\Widgets\VoucherCartStatsWidget;
use AIArmada\FilamentVouchers\Widgets\VoucherStatsWidget;
use AIArmada\FilamentVouchers\Widgets\VoucherSuggestionsWidget;
use AIArmada\FilamentVouchers\Widgets\VoucherUsageTimelineWidget;
use AIArmada\FilamentVouchers\Widgets\VoucherWalletStatsWidget;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

uses(TestCase::class);

afterEach(function (): void {
    \Mockery::close();
});

function makeVouchersTable(): Table
{
    /** @var HasTable $livewire */
    $livewire = \Mockery::mock(HasTable::class);

    return Table::make($livewire);
}

it('builds resources, schemas, tables, relation managers, pages, widgets, and actions', function (): void {
    // Resources
    foreach ([
        VoucherResource::class,
        CampaignResource::class,
        GiftCardResource::class,
        VoucherUsageResource::class,
        VoucherWalletResource::class,
        FraudSignalResource::class,
    ] as $resource) {
        expect($resource::form(Schema::make()))->toBeInstanceOf(Schema::class);
        expect($resource::infolist(Schema::make()))->toBeInstanceOf(Schema::class);
        expect($resource::table(makeVouchersTable()))->toBeInstanceOf(Table::class);
        expect($resource::getPages())->toBeArray();
    }

    // Schema/table builders
    expect(CampaignForm::configure(Schema::make()))->toBeInstanceOf(Schema::class);
    expect(CampaignInfolist::configure(Schema::make()))->toBeInstanceOf(Schema::class);
    expect(CampaignsTable::configure(makeVouchersTable()))->toBeInstanceOf(Table::class);

    expect(VoucherForm::configure(Schema::make()))->toBeInstanceOf(Schema::class);
    expect(VoucherInfolist::configure(Schema::make()))->toBeInstanceOf(Schema::class);
    expect(VouchersTable::configure(makeVouchersTable()))->toBeInstanceOf(Table::class);
    expect(WalletEntriesTable::configure(makeVouchersTable()))->toBeInstanceOf(Table::class);

    expect(GiftCardForm::configure(Schema::make()))->toBeInstanceOf(Schema::class);
    expect(GiftCardInfolist::configure(Schema::make()))->toBeInstanceOf(Schema::class);
    expect(GiftCardsTable::configure(makeVouchersTable()))->toBeInstanceOf(Table::class);

    expect(FraudSignalInfolist::configure(Schema::make()))->toBeInstanceOf(Schema::class);
    expect(FraudSignalsTable::configure(makeVouchersTable()))->toBeInstanceOf(Table::class);

    expect(VoucherUsagesTable::configure(makeVouchersTable()))->toBeInstanceOf(Table::class);
    expect(VoucherWalletsTable::configure(makeVouchersTable()))->toBeInstanceOf(Table::class);

    // Relation managers
    foreach ([
        VoucherUsagesRelationManager::class,
        WalletEntriesRelationManager::class,
        VariantsRelationManager::class,
        VouchersRelationManager::class,
        TransactionsRelationManager::class,
    ] as $manager) {
        $instance = app($manager);
        expect($instance->table(makeVouchersTable()))->toBeInstanceOf(Table::class);
    }

    // Resource pages: invoke protected action builders via reflection.
    foreach ([
        EditCampaign::class,
        ListCampaigns::class,
        ViewCampaign::class,
        ListFraudSignals::class,
        ViewFraudSignal::class,
        ListVoucherUsages::class,
        EditGiftCard::class,
        ListGiftCards::class,
        ViewGiftCard::class,
        EditVoucher::class,
        ListVouchers::class,
        ViewVoucher::class,
    ] as $pageClass) {
        $page = app($pageClass);

        $method = new ReflectionMethod($pageClass, 'getHeaderActions');
        $method->setAccessible(true);

        expect($method->invoke($page))->toBeArray();
    }

    foreach ([CreateGiftCard::class, CreateVoucher::class] as $pageClass) {
        $page = app($pageClass);

        $method = new ReflectionMethod($pageClass, 'getFormActions');
        $method->setAccessible(true);

        expect($method->invoke($page))->toBeArray();
    }

    // Standalone pages
    foreach ([
        ABTestDashboard::class,
        FraudConfigurationPage::class,
        StackingConfigurationPage::class,
        TargetingConfigurationPage::class,
    ] as $page) {
        expect(app($page))->toBeInstanceOf($page);
    }

    // Widgets
    foreach ([
        VoucherStatsWidget::class,
        CampaignStatsWidget::class,
        RedemptionTrendChart::class,
        GiftCardStatsWidget::class,
        FraudStatsWidget::class,
        AIInsightsWidget::class,
        AppliedVoucherBadgesWidget::class,
        AppliedVouchersWidget::class,
        QuickApplyVoucherWidget::class,
        VoucherSuggestionsWidget::class,
        VoucherUsageTimelineWidget::class,
        VoucherWalletStatsWidget::class,
        VoucherCartStatsWidget::class,
        GiftCardTransactionTimelineWidget::class,
    ] as $widget) {
        expect(app($widget))->toBeInstanceOf($widget);
    }

    // Actions
    foreach ([
        ActivateVoucherAction::class,
        PauseVoucherAction::class,
        ActivateCampaignAction::class,
        PauseCampaignAction::class,
        ActivateGiftCardAction::class,
        SuspendGiftCardAction::class,
        TopUpGiftCardAction::class,
        AddToMyWalletAction::class,
        ManualRedeemVoucherAction::class,
        BulkGenerateVouchersAction::class,
        BulkIssueGiftCardsAction::class,
        MarkFraudReviewedAction::class,
        DeclareABWinnerAction::class,
        ApplyVoucherToCartAction::class,
    ] as $action) {
        expect($action::make())->toBeObject();
    }

    // Extensions
    expect(CartVoucherActions::applyVoucher())->toBeInstanceOf(\Filament\Actions\Action::class);
    expect(CartVoucherActions::showAppliedVouchers())->toBeInstanceOf(\Filament\Actions\Action::class);
    expect(CartVoucherActions::removeVoucher('TEST'))->toBeInstanceOf(\Filament\Actions\Action::class);

    // Plugin
    $panel = Panel::make()->id('admin');
    (new FilamentVouchersPlugin)->register($panel);
});
