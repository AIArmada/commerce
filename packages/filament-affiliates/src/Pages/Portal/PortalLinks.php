<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages\Portal;

use AIArmada\FilamentAffiliates\Concerns\PortalPage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;

class PortalLinks extends PortalPage
{
    public string $targetUrl = '';

    public ?string $generatedLink = null;

    public ?string $generatedShortLink = null;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedLink;

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.portal.links';

    public static function getNavigationLabel(): string
    {
        return __('Links');
    }

    public function getTitle(): string | Htmlable
    {
        return __('Affiliate Links');
    }

    public function mount(): void
    {
        $this->targetUrl = $this->resolvePublicUrl();
    }

    public function getViewData(): array
    {
        $affiliate = $this->getAffiliate();

        return [
            'affiliate' => $affiliate,
            'hasAffiliate' => $this->hasAffiliate(),
            'affiliateCode' => $affiliate?->code,
            'defaultLink' => $this->getDefaultLink(),
            'shortLink' => $this->getShortLink(),
        ];
    }

    #[Computed]
    public function getDefaultLink(): ?string
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return null;
        }

        $param = config('affiliates.links.parameter', 'aff');

        return $this->resolvePublicUrl() . '?' . $param . '=' . $affiliate->code;
    }

    #[Computed]
    public function getShortLink(): ?string
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return null;
        }

        return mb_rtrim((string) config('app.url'), '/') . '/r/' . $affiliate->code;
    }

    public function generateLink(): void
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            Notification::make()
                ->title(__('No affiliate account'))
                ->danger()
                ->send();

            return;
        }

        if ($this->targetUrl === '') {
            $this->targetUrl = $this->resolvePublicUrl();
        }

        $allowedHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $targetHost = parse_url($this->targetUrl, PHP_URL_HOST);

        if ($targetHost === null || $targetHost !== $allowedHost) {
            Notification::make()
                ->title(__('Invalid URL'))
                ->body(__('Only links to :host are allowed.', ['host' => $allowedHost]))
                ->danger()
                ->send();

            return;
        }

        $param = config('affiliates.links.parameter', 'aff');

        $this->generatedLink = $this->targetUrl
            . (str_contains($this->targetUrl, '?') ? '&' : '?')
            . $param . '=' . $affiliate->code;

        $path = mb_ltrim((string) parse_url($this->targetUrl, PHP_URL_PATH), '/');

        $this->generatedShortLink = mb_rtrim((string) config('app.url'), '/')
            . ($path !== '' ? '/' . $path : '')
            . '/r/' . $affiliate->code;

        Notification::make()
            ->title(__('Link generated successfully'))
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateLink')
                ->label(__('Generate Link'))
                ->form([
                    TextInput::make('url')
                        ->label(__('Target URL'))
                        ->url()
                        ->required()
                        ->default($this->resolvePublicUrl())
                        ->placeholder('https://example.com/product'),
                ])
                ->action(function (array $data): void {
                    $this->targetUrl = $data['url'];
                    $this->generateLink();
                }),
        ];
    }

    private function resolvePublicUrl(): string
    {
        return mb_rtrim((string) config('app.url'), '/') . '/';
    }
}
