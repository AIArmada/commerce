<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\FilamentCommerceSupport\Concerns\AuditsRelatedStateChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Livewire\Attributes\Locked;
use Livewire\Component as LivewireComponent;
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

    $this->record = new class extends Model implements AuditableContract
    {
        use HasCommerceAudit;

        protected $guarded = [];
    };
    $this->record->forceFill(['status' => 'draft']);

    $this->page = new class extends LivewireComponent
    {
        use AuditsRelatedStateChanges;

        public function capture(Model $record): void
        {
            $this->captureRelatedAuditSnapshot($record);
        }

        public function audit(Model $record, string $event): void
        {
            $this->auditRelatedStateChanges($record, $event);
        }

        protected function getRelatedAuditSnapshot(Model $record): array
        {
            return ['status' => $record->getAttribute('status')];
        }

        public function render(): string
        {
            return '';
        }
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

it('audits related state changes between capture and audit', function (): void {
    $this->page->capture($this->record);
    $this->record->forceFill(['status' => 'active']);
    $this->page->audit($this->record, 'related_updated');

    expect($this->captured)->toBe(['related_updated', ['status' => 'draft'], ['status' => 'active']]);
});

it('records nothing without changes', function (): void {
    $this->page->capture($this->record);
    $this->page->audit($this->record, 'related_updated');

    expect($this->captured)->toBeNull();
});

it('skips records that are not auditable', function (): void {
    $plain = new class extends Model
    {
        protected $guarded = [];
    };

    $this->page->capture($plain);
    $this->page->audit($plain, 'related_updated');

    expect($this->captured)->toBeNull();
});

it('skips audited models that do not implement the auditing contract', function (): void {
    $uncontracted = new class extends Model
    {
        use HasCommerceAudit;

        protected $guarded = [];
    };

    $this->page->capture($uncontracted);
    $this->page->audit($uncontracted, 'related_updated');

    expect($this->captured)->toBeNull();
});

it('locks the snapshot against frontend tampering', function (): void {
    $property = new ReflectionProperty($this->page, 'relatedAuditSnapshot');

    expect($property->getAttributes(Locked::class))->not->toBeEmpty();
});
