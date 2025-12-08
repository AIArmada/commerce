<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Listeners;

use AIArmada\FilamentAuthz\Enums\AuditEventType;
use AIArmada\FilamentAuthz\Enums\AuditSeverity;
use AIArmada\FilamentAuthz\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;

class PermissionEventSubscriber
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): void
    {
        // Auth events
        $events->listen(Login::class, [$this, 'handleLogin']);
        $events->listen(Logout::class, [$this, 'handleLogout']);
        $events->listen(Failed::class, [$this, 'handleFailedLogin']);
        $events->listen(PasswordReset::class, [$this, 'handlePasswordReset']);

        // Spatie Permission events (if using event dispatcher)
        $events->listen('Spatie\\Permission\\Events\\RoleCreated', [$this, 'handleRoleCreated']);
        $events->listen('Spatie\\Permission\\Events\\RoleDeleted', [$this, 'handleRoleDeleted']);
        $events->listen('Spatie\\Permission\\Events\\PermissionCreated', [$this, 'handlePermissionCreated']);
        $events->listen('Spatie\\Permission\\Events\\PermissionDeleted', [$this, 'handlePermissionDeleted']);
    }

    public function handleLogin(Login $event): void
    {
        $user = $event->user;
        if ($user instanceof Model) {
            $this->auditLogger->log(
                eventType: AuditEventType::UserLogin,
                subject: $user
            );
        }
    }

    public function handleLogout(Logout $event): void
    {
        $user = $event->user;
        if ($user instanceof Model) {
            $this->auditLogger->log(
                eventType: AuditEventType::UserLogout,
                subject: $user
            );
        }
    }

    public function handleFailedLogin(Failed $event): void
    {
        $this->auditLogger->log(
            eventType: AuditEventType::LoginFailed,
            metadata: [
                'credentials' => $event->credentials,
            ],
            severity: AuditSeverity::Medium
        );
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $user = $event->user;
        if ($user instanceof Model) {
            $this->auditLogger->log(
                eventType: AuditEventType::PasswordChanged,
                subject: $user
            );
        }
    }

    /**
     * @param  object  $event
     */
    public function handleRoleCreated($event): void
    {
        if (property_exists($event, 'role') && $event->role instanceof Model) {
            $this->auditLogger->logRoleCreated($event->role);
        }
    }

    /**
     * @param  object  $event
     */
    public function handleRoleDeleted($event): void
    {
        if (property_exists($event, 'role') && $event->role instanceof Model) {
            $this->auditLogger->logRoleDeleted($event->role);
        }
    }

    /**
     * @param  object  $event
     */
    public function handlePermissionCreated($event): void
    {
        if (property_exists($event, 'permission') && $event->permission instanceof Model) {
            $this->auditLogger->log(
                eventType: AuditEventType::PermissionCreated,
                subject: $event->permission
            );
        }
    }

    /**
     * @param  object  $event
     */
    public function handlePermissionDeleted($event): void
    {
        if (property_exists($event, 'permission') && $event->permission instanceof Model) {
            $this->auditLogger->log(
                eventType: AuditEventType::PermissionDeleted,
                subject: $event->permission
            );
        }
    }
}
