<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages\Portal;

use AIArmada\Affiliates\Actions\Affiliates\CreateTrackingLink;
use AIArmada\FilamentAffiliates\Concerns\PortalPage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;

class PortalLinks extends PortalPage
{
    public string $targetUrl = '';

    public ?string $generatedLink = null;

    public string $linkStyle = 'short';

    public string $linkLabel = '';

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
        $this->linkStyle = (string) config('affiliates.links.default_style', 'short');
    }

    public function getViewData(): array
    {
        $affiliate = $this->getAffiliate();

        return [
            'affiliate' => $affiliate,
            'hasAffiliate' => $this->hasAffiliate(),
            'affiliateCode' => $affiliate?->code,
            'defaultLink' => $this->getDefaultLink(),
            'affiliateHandle' => $affiliate?->handle,
        ];
    }

    #[Computed]
    public function getDefaultLink(): ?string
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return null;
        }

        return $affiliate->links()->where('destination_url', $this->resolvePublicUrl())
            ->whereNull('deactivated_at')->latest()->value('tracking_url');
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

        $allowedHost = mb_strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        $validated = validator(
            ['target_url' => $this->targetUrl],
            ['target_url' => ['required', 'string', 'max:2048', 'url:http,https']],
        )->validate();

        $targetUrl = $validated['target_url'];
        $targetHost = mb_strtolower((string) parse_url($targetUrl, PHP_URL_HOST));

        $hostAllowed = $targetHost !== ''
            && ($targetHost === $allowedHost || str_ends_with($targetHost, '.' . $allowedHost));

        if (! $hostAllowed) {
            Notification::make()
                ->title(__('Invalid URL'))
                ->body(__('Only links to :host are allowed.', ['host' => $allowedHost]))
                ->danger()
                ->send();

            return;
        }

        $link = CreateTrackingLink::run($affiliate, $targetUrl, [
            'link_style' => $this->linkStyle,
            'link_label' => $this->linkLabel,
        ]);
        $this->generatedLink = $link->tracking_url;

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
                    Select::make('link_style')->label('Link Style')->options(['short' => 'Short', 'branded' => 'Branded'])->default(config('affiliates.links.default_style', 'short'))->required(),
                    TextInput::make('link_label')->label('Campaign Name')->maxLength(60),
                    TextInput::make('url')
                        ->label(__('Target URL'))
                        ->url()
                        ->required()
                        ->default($this->resolvePublicUrl())
                        ->placeholder('https://example.com/product'),
                ])
                ->action(function (array $data): void {
                    $this->linkStyle = $data['link_style'];
                    $this->linkLabel = $data['link_label'] ?? '';
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
