<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Pages;

use AIArmada\Chip\Services\ChipCollectService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Throwable;

class WebhookConfigPage extends Page
{
    /** @var array<int, array<string, mixed>> */
    public array $webhooks = [];

    public bool $hasError = false;

    public string $errorMessage = '';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBell;

    protected static ?string $navigationLabel = 'Webhook Config';

    protected static ?string $title = 'Webhook Configuration';

    protected static ?string $slug = 'chip/webhooks/config';

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-chip.navigation.group', 'Payments');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public function mount(): void
    {
        $this->loadWebhooks();
    }

    public function loadWebhooks(): void
    {
        $this->hasError = false;
        $this->errorMessage = '';

        try {
            $service = app(ChipCollectService::class);
            $response = $service->listWebhooks();

            $this->webhooks = $response['data'] ?? $response ?? [];
        } catch (Throwable $e) {
            $this->hasError = true;
            $this->errorMessage = $e->getMessage();
            $this->webhooks = [];
        }
    }

    public function deleteWebhook(string $webhookId): void
    {
        $service = app(ChipCollectService::class);

        try {
            $service->deleteWebhook($webhookId);
            Notification::make()
                ->title('Webhook deleted')
                ->success()
                ->send();
            $this->loadWebhooks();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Failed to delete webhook')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function render(): View
    {
        return view('filament-chip::pages.webhook-config', [
            'webhooks' => $this->webhooks,
            'hasError' => $this->hasError,
            'errorMessage' => $this->errorMessage,
        ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon(Heroicon::ArrowPath)
                ->action(function (): void {
                    $this->loadWebhooks();
                    Notification::make()
                        ->title('Webhooks refreshed')
                        ->success()
                        ->send();
                }),

            Action::make('view_logs')
                ->label('View Webhook Logs')
                ->icon(Heroicon::QueueList)
                ->color('info')
                ->url(fn (): string => route('filament.admin.pages.chip-webhook-monitor')),
        ];
    }
}
