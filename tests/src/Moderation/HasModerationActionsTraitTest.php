<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\Moderation\Enums\ModerationActionType;
use AIArmada\Moderation\Models\ModerationAction;
use AIArmada\Moderation\Traits\HasModerationActions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class ActionableTestModel extends Model
{
    use HasModerationActions;
    use HasUuids;

    protected $table = 'actionable_test_models';

    protected $fillable = ['name'];
}

beforeEach(function (): void {
    Schema::dropIfExists('actionable_test_models');

    Schema::create('actionable_test_models', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    $this->model = ActionableTestModel::create(['name' => 'Actionable Test']);
});

afterEach(function (): void {
    Schema::dropIfExists('actionable_test_models');
});

test('records a moderation action for a global model when owner scoping is enabled', function (): void {
    config()->set('moderation.features.owner.enabled', true);

    $action = $this->model->recordModerationAction(
        ModerationActionType::Warn,
        'Manual review required',
    );

    expect($action)->toBeInstanceOf(ModerationAction::class);
    expect($action->actionable_id)->toBe($this->model->id);
    expect($action->metadata)->toBeNull();
});

test('rejects a cross-owner actionedBy model', function (): void {
    config()->set('moderation.features.owner.enabled', true);

    $otherOwner = User::create([
        'name' => 'Other Owner',
        'email' => 'action-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $actionedBy = OwnerContext::withOwner($otherOwner, function (): Customer {
        return Customer::factory()->create();
    });

    expect(fn (): ModerationAction => $this->model->recordModerationAction(
        ModerationActionType::Approve,
        'Approved by reviewer',
        actionedById: $actionedBy->id,
        actionedByType: Customer::class,
    ))->toThrow(AuthorizationException::class);
});
