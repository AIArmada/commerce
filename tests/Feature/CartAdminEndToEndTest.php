<?php

declare(strict_types=1);

namespace Tests\src\FilamentCart\Integration;

use AIArmada\Cart\Models\AlertRule;
use AIArmada\Cart\Models\RecoveryCampaign;
use AIArmada\Cart\Models\RecoveryTemplate;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Resources\AlertRuleResource;
use AIArmada\FilamentCart\Resources\RecoveryCampaignResource;
use AIArmada\FilamentCart\Resources\RecoveryTemplateResource;
use Livewire\Livewire;

uses(TestCase::class);

describe('Recovery Templates End-to-End', function (): void {
    it('can render recovery templates list page', function (): void {
        $user = createUserWithRoles(['Super Admin']);
        test()->actingAs($user);

        Livewire::test(RecoveryTemplateResource\Pages\ListRecoveryTemplates::class)
            ->assertSuccessful();
    });

    it('can render recovery template create page', function (): void {
        $this->actingAsAdmin();

        Livewire::test(RecoveryTemplateResource\Pages\CreateRecoveryTemplate::class)
            ->assertSuccessful();
    });

    it('can create a recovery template', function (): void {
        $this->actingAsAdmin();

        $data = [
            'name' => 'First Reminder',
            'description' => 'Send 1 hour after abandonment',
            'type' => 'email',
            'status' => 'active',
            'is_default' => false,
            'subject' => 'You left something in your cart!',
            'email_body' => 'Hi {{customer_name}}, complete your purchase: {{cart_url}}',
        ];

        Livewire::test(RecoveryTemplateResource\Pages\CreateRecoveryTemplate::class)
            ->fillForm($data)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(RecoveryTemplate::class, [
            'name' => 'First Reminder',
            'type' => 'email',
            'status' => 'active',
        ]);
    });

    it('validates required fields when creating recovery template', function (): void {
        $this->actingAsAdmin();

        Livewire::test(RecoveryTemplateResource\Pages\CreateRecoveryTemplate::class)
            ->fillForm([
                'name' => '',
                'type' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['name', 'type']);
    });

    it('can edit a recovery template', function (): void {
        $this->actingAsAdmin();

        $template = RecoveryTemplate::factory()->create([
            'name' => 'Old Name',
            'type' => 'email',
            'status' => 'draft',
        ]);

        Livewire::test(RecoveryTemplateResource\Pages\EditRecoveryTemplate::class, [
            'record' => $template->id,
        ])
            ->assertFormSet([
                'name' => 'Old Name',
            ])
            ->fillForm([
                'name' => 'Updated Name',
                'status' => 'active',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($template->fresh())
            ->name->toBe('Updated Name')
            ->status->toBe('active');
    });

    it('can view a recovery template', function (): void {
        $this->actingAsAdmin();

        $template = RecoveryTemplate::factory()->create();

        Livewire::test(RecoveryTemplateResource\Pages\ViewRecoveryTemplate::class, [
            'record' => $template->id,
        ])
            ->assertSuccessful()
            ->assertSee($template->name);
    });

    it('can delete a recovery template', function (): void {
        $this->actingAsAdmin();

        $template = RecoveryTemplate::factory()->create();

        Livewire::test(RecoveryTemplateResource\Pages\EditRecoveryTemplate::class, [
            'record' => $template->id,
        ])
            ->callAction('delete');

        $this->assertModelMissing($template);
    });
});

describe('Recovery Campaigns End-to-End', function (): void {
    it('can render recovery campaigns list page', function (): void {
        $this->actingAsAdmin();

        Livewire::test(RecoveryCampaignResource\Pages\ListRecoveryCampaigns::class)
            ->assertSuccessful();
    });

    it('can render recovery campaign create page', function (): void {
        $this->actingAsAdmin();

        Livewire::test(RecoveryCampaignResource\Pages\CreateRecoveryCampaign::class)
            ->assertSuccessful();
    });

    it('can create a recovery campaign', function (): void {
        $this->actingAsAdmin();

        $controlTemplate = RecoveryTemplate::factory()->create(['type' => 'email']);
        $variantTemplate = RecoveryTemplate::factory()->create(['type' => 'email']);

        $data = [
            'name' => 'Holiday Recovery Campaign',
            'description' => 'Target holiday shoppers',
            'status' => 'active',
            'trigger_type' => 'abandoned',
            'trigger_delay_minutes' => 60,
            'max_attempts' => 3,
            'attempt_interval_hours' => 24,
            'min_cart_value_cents' => 1000,
            'max_cart_value_cents' => null,
            'control_template_id' => $controlTemplate->id,
            'ab_testing_enabled' => true,
            'variant_template_id' => $variantTemplate->id,
            'ab_test_split_percent' => 50,
        ];

        Livewire::test(RecoveryCampaignResource\Pages\CreateRecoveryCampaign::class)
            ->fillForm($data)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(RecoveryCampaign::class, [
            'name' => 'Holiday Recovery Campaign',
            'status' => 'active',
            'trigger_type' => 'abandoned',
        ]);
    });

    it('validates required fields when creating recovery campaign', function (): void {
        $this->actingAsAdmin();

        Livewire::test(RecoveryCampaignResource\Pages\CreateRecoveryCampaign::class)
            ->fillForm([
                'name' => '',
                'trigger_type' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['name', 'trigger_type']);
    });

    it('can edit a recovery campaign', function (): void {
        $this->actingAsAdmin();

        $campaign = RecoveryCampaign::factory()->create([
            'name' => 'Old Campaign',
            'status' => 'draft',
        ]);

        Livewire::test(RecoveryCampaignResource\Pages\EditRecoveryCampaign::class, [
            'record' => $campaign->id,
        ])
            ->fillForm([
                'name' => 'Updated Campaign',
                'status' => 'active',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($campaign->fresh())
            ->name->toBe('Updated Campaign')
            ->status->toBe('active');
    });

    it('can view a recovery campaign', function (): void {
        $this->actingAsAdmin();

        $campaign = RecoveryCampaign::factory()->create();

        Livewire::test(RecoveryCampaignResource\Pages\ViewRecoveryCampaign::class, [
            'record' => $campaign->id,
        ])
            ->assertSuccessful()
            ->assertSee($campaign->name);
    });
});

describe('Alert Rules End-to-End', function (): void {
    it('can render alert rules list page', function (): void {
        $this->actingAsAdmin();

        Livewire::test(AlertRuleResource\Pages\ListAlertRules::class)
            ->assertSuccessful();
    });

    it('can render alert rule create page', function (): void {
        $this->actingAsAdmin();

        Livewire::test(AlertRuleResource\Pages\CreateAlertRule::class)
            ->assertSuccessful();
    });

    it('can create an alert rule', function (): void {
        $this->actingAsAdmin();

        $data = [
            'name' => 'High Value Cart Alert',
            'description' => 'Alert for carts over $500',
            'event_type' => 'high_value',
            'severity' => 'warning',
            'priority' => 10,
            'is_active' => true,
            'conditions' => [
                [
                    'field' => 'total',
                    'operator' => 'greater_than',
                    'value' => '50000',
                ],
            ],
            'notification_channels' => ['email'],
            'notification_recipients' => ['admin@example.com'],
        ];

        Livewire::test(AlertRuleResource\Pages\CreateAlertRule::class)
            ->fillForm($data)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(AlertRule::class, [
            'name' => 'High Value Cart Alert',
            'event_type' => 'high_value',
            'severity' => 'warning',
        ]);
    });

    it('validates required fields when creating alert rule', function (): void {
        $this->actingAsAdmin();

        Livewire::test(AlertRuleResource\Pages\CreateAlertRule::class)
            ->fillForm([
                'name' => '',
                'event_type' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['name', 'event_type']);
    });

    it('can edit an alert rule', function (): void {
        $this->actingAsAdmin();

        $rule = AlertRule::factory()->create([
            'name' => 'Old Alert',
            'is_active' => false,
        ]);

        Livewire::test(AlertRuleResource\Pages\EditAlertRule::class, [
            'record' => $rule->id,
        ])
            ->fillForm([
                'name' => 'Updated Alert',
                'is_active' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($rule->fresh())
            ->name->toBe('Updated Alert')
            ->is_active->toBeTrue();
    });

    it('can toggle alert rule active status', function (): void {
        $this->actingAsAdmin();

        $rule = AlertRule::factory()->create(['is_active' => true]);

        Livewire::test(AlertRuleResource\Pages\ListAlertRules::class)
            ->callTableAction('toggle_active', $rule);

        expect($rule->fresh()->is_active)->toBeFalse();
    });
});

describe('Integration: Recovery Flow', function (): void {
    it('completes full recovery workflow from template to campaign', function (): void {
        $this->actingAsAdmin();

        // Step 1: Create a template
        $template = RecoveryTemplate::factory()->create([
            'name' => 'Workflow Template',
            'type' => 'email',
            'status' => 'active',
            'subject' => 'Complete your purchase',
            'email_body' => 'Hi {{customer_name}}, your cart is waiting!',
        ]);

        expect($template->status)->toBe('active');

        // Step 2: Create a campaign using the template
        $campaign = RecoveryCampaign::factory()->create([
            'name' => 'Workflow Campaign',
            'status' => 'active',
            'trigger_type' => 'abandoned',
            'control_template_id' => $template->id,
            'trigger_delay_minutes' => 60,
            'max_attempts' => 2,
        ]);

        expect($campaign->controlTemplate)->not->toBeNull()
            ->and($campaign->controlTemplate->id)->toBe($template->id);

        // Step 3: Verify campaign is ready
        expect($campaign->isActive())->toBeTrue()
            ->and($campaign->controlTemplate->status)->toBe('active');
    });
});

describe('Integration: Alert Monitoring', function (): void {
    it('creates alert rule that can monitor cart conditions', function (): void {
        $this->actingAsAdmin();

        // Create an alert rule for high-value carts
        $alertRule = AlertRule::factory()->create([
            'name' => 'Monitor High Value',
            'event_type' => 'high_value',
            'severity' => 'info',
            'is_active' => true,
            'conditions' => [
                ['field' => 'total', 'operator' => 'greater_than', 'value' => '10000'],
            ],
        ]);

        expect($alertRule->is_active)->toBeTrue()
            ->and($alertRule->conditions)->toBeArray()
            ->and($alertRule->conditions)->toHaveCount(1);
    });
});
