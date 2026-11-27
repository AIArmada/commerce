<?php

declare(strict_types=1);

namespace AIArmada\Communications\Integrations\LaravelAuditing;

use AIArmada\Communications\Runtime\PreferenceAuditModel;
use AIArmada\Communications\Runtime\SuppressionAuditModel;
use AIArmada\Communications\Runtime\TemplateRelationModels;
use OwenIt\Auditing\Auditable;

trait AuditableCommunication
{
    public function initializeAuditableCommunication(): void
    {
        if (! class_exists(Auditable::class)) {
            return;
        }

        $this->mergeCasts([
            'metadata' => 'array',
        ]);
    }

    public function shouldAudit(): bool
    {
        if (! class_exists(Auditable::class)) {
            return false;
        }

        if (! config('communications.integrations.laravel_auditing.enabled', false)) {
            return false;
        }

        return $this->isAuditableModel();
    }

    private function isAuditableModel(): bool
    {
        $auditableModels = [
            app(TemplateRelationModels::class)->template(),
            app(TemplateRelationModels::class)->version(),
            app(PreferenceAuditModel::class)->preference(),
            app(SuppressionAuditModel::class)->suppression(),
        ];

        return in_array(static::class, $auditableModels, true);
    }

    public function getAuditData(): array
    {
        $data = $this->toArray();

        unset(
            $data['content_text'],
            $data['content_html'],
            $data['payload'],
            $data['destination_hash'],
        );

        return $data;
    }

    public function getAuditExclude(): array
    {
        return [
            'content_text',
            'content_html',
            'payload',
            'destination_hash',
        ];
    }
}
