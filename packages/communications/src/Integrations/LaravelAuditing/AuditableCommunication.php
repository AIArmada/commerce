<?php

declare(strict_types=1);

namespace AIArmada\Communications\Integrations\LaravelAuditing;

use AIArmada\Communications\Models\CommunicationPreference;
use AIArmada\Communications\Models\CommunicationSuppression;
use AIArmada\Communications\Models\CommunicationTemplate;
use AIArmada\Communications\Models\CommunicationTemplateVersion;
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
            CommunicationTemplate::class,
            CommunicationTemplateVersion::class,
            CommunicationPreference::class,
            CommunicationSuppression::class,
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
