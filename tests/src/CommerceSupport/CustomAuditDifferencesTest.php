<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Contracts\UserResolver;
use OwenIt\Auditing\Events\AuditCustom;

beforeEach(function (): void {
    $nullUserResolver = new class implements UserResolver
    {
        public static function resolve()
        {
            return null;
        }
    };

    config()->set('audit.user.resolver', $nullUserResolver::class);

    $this->model = new class extends Model implements AuditableContract
    {
        use HasCommerceAudit;

        protected $guarded = [];
    };

    $this->captured = null;

    Event::listen(AuditCustom::class, function (AuditCustom $event): void {
        $this->captured = [
            $event->model->auditEvent,
            $event->model->auditCustomOld ?? null,
            $event->model->auditCustomNew ?? null,
        ];
    });
});

it('records custom audits with explicit old and new values', function (): void {
    $this->model->recordCustomAudit('related_updated', ['status' => 'draft'], ['status' => 'active']);

    expect($this->captured)->toBe(['related_updated', ['status' => 'draft'], ['status' => 'active']]);
});

it('records nothing for empty value sets', function (): void {
    $this->model->recordCustomAudit('related_updated', [], []);

    expect($this->captured)->toBeNull();
});

it('restores the previous audit state after dispatch', function (): void {
    $this->model->recordCustomAudit('related_updated', ['a' => 1], ['a' => 2]);

    expect($this->model->auditEvent)->toBeNull()
        ->and($this->model->isCustomEvent)->toBeFalse()
        ->and($this->model->auditCustomOld)->toBe([])
        ->and($this->model->auditCustomNew)->toBe([]);
});

it('restores the previous audit state when a listener throws', function (): void {
    Event::listen(AuditCustom::class, function (): void {
        throw new RuntimeException('listener boom');
    });

    expect(fn () => $this->model->recordCustomAudit('related_updated', ['a' => 1], ['a' => 2]))
        ->toThrow(RuntimeException::class, 'listener boom');

    expect($this->model->auditEvent)->toBeNull()
        ->and($this->model->isCustomEvent)->toBeFalse()
        ->and($this->model->auditCustomOld)->toBe([])
        ->and($this->model->auditCustomNew)->toBe([]);
});

it('diffs snapshots and skips values equal by meaning', function (): void {
    $this->model->recordCustomAuditDifferences('related_updated', [
        'status' => 'draft',
        'owner_id' => 5,
        'starts_at' => Carbon::parse('2026-01-01 10:00:00'),
        'removed' => true,
    ], [
        'status' => 'active',
        'owner_id' => '5',
        'starts_at' => CarbonImmutable::parse('2026-01-01 10:00:00'),
        'added' => true,
    ]);

    expect($this->captured)->toBe([
        'related_updated',
        ['status' => 'draft', 'removed' => true],
        ['status' => 'active', 'added' => true],
    ]);
});

it('records nothing when snapshots match', function (): void {
    $this->model->recordCustomAuditDifferences('related_updated', ['status' => 'draft'], ['status' => 'draft']);

    expect($this->captured)->toBeNull();
});
