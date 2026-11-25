<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Actions;

use AIArmada\Authz\Services\ImpersonateManager;
use AIArmada\Authz\Support\BackToUrlSanitizer;
use Filament\Actions\Action;
use Filament\Navigation\MenuItem;
use Filament\Notifications\Notification;

/**
 * Action to leave impersonation and return to the original user.
 *
 * @example
 * ```php
 * use AIArmada\FilamentAuthz\Actions\LeaveImpersonationAction;
 *
 * // In your panel provider:
 * ->userMenuItems([
 *     LeaveImpersonationAction::make()->asMenuItem(),
 * ])
 * ```
 */
class LeaveImpersonationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'leave-impersonation';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-authz::filament-authz.impersonate.leave'))
            ->icon('heroicon-o-arrow-left-on-rectangle')
            ->color('danger')
            ->visible(fn (): bool => app(ImpersonateManager::class)->isImpersonating())
            ->action(function (): void {
                $manager = app(ImpersonateManager::class);
                $backTo = $manager->getBackTo();

                if (! $manager->leave()) {
                    Notification::make()
                        ->danger()
                        ->title(__('filament-authz::filament-authz.impersonate.failed_title'))
                        ->send();

                    return;
                }

                $this->redirect(BackToUrlSanitizer::sanitize($backTo), navigate: false);
            });
    }

    public function asMenuItem(): MenuItem
    {
        return MenuItem::make()
            ->label(__('filament-authz::filament-authz.impersonate.leave'))
            ->icon('heroicon-o-arrow-left-on-rectangle')
            ->color('danger')
            ->visible(fn (): bool => app(ImpersonateManager::class)->isImpersonating())
            ->postAction(route('filament-authz.impersonate.leave'));
    }
}
