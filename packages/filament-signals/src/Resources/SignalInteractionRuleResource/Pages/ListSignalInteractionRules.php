<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalInteractionRuleResource\Pages;

use AIArmada\FilamentSignals\Resources\SignalInteractionRuleResource;
use AIArmada\FilamentSignals\Support\InteractionRuleScanner;
use AIArmada\Signals\Models\SignalInteractionRule;
use AIArmada\Signals\Models\TrackedProperty;
use Filament\Actions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

final class ListSignalInteractionRules extends ListRecords
{
    protected static string $resource = SignalInteractionRuleResource::class;

    public function getTitle(): string
    {
        return 'Interaction Rules';
    }

    public function getSubheading(): ?string
    {
        return 'Choose which clicks and UI interactions should emit Signals events from the browser tracker. You can scan live URLs or local Blade/Livewire sources.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New interaction rule'),
            Actions\Action::make('createFromPreview')
                ->label('Create from preview')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => $this->scanPreviewPayload()['candidates'] !== [])
                ->modalHeading('Create rules from scan preview')
                ->modalDescription('Review detected candidates and choose which ones to turn into interaction rules.')
                ->form([
                    Placeholder::make('preview_source')
                        ->label('Preview source')
                        ->content(fn (): string => (string) ($this->scanPreviewPayload()['meta']['source_label'] ?? 'Unknown source')),
                    CheckboxList::make('candidate_indexes')
                        ->label('Detected candidates')
                        ->options(fn (): array => $this->previewCandidateOptions())
                        ->descriptions(fn (): array => $this->previewCandidateDescriptions())
                        ->columns(1)
                        ->bulkToggleable()
                        ->required()
                        ->default(fn (): array => array_keys($this->previewCandidateOptions())),
                    Toggle::make('activate_created_rules')
                        ->label('Activate created rules')
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    $payload = $this->scanPreviewPayload();
                    $candidates = $payload['candidates'];

                    if ($candidates === []) {
                        Notification::make()
                            ->title('No preview candidates found')
                            ->warning()
                            ->send();

                        return;
                    }

                    $selectedIndexes = array_values(array_filter(
                        array_map(static fn (mixed $index): int => (int) $index, is_array($data['candidate_indexes'] ?? null) ? $data['candidate_indexes'] : []),
                        static fn (int $index): bool => array_key_exists($index, $candidates),
                    ));

                    if ($selectedIndexes === []) {
                        Notification::make()
                            ->title('No candidates selected')
                            ->warning()
                            ->send();

                        return;
                    }

                    $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
                    $eventName = (string) ($meta['event_name'] ?? 'ui.click');
                    $eventCategoryRaw = (string) ($meta['event_category'] ?? 'engagement');
                    $eventCategory = mb_trim($eventCategoryRaw) !== '' ? $eventCategoryRaw : null;
                    $triggerType = (string) ($meta['trigger_type'] ?? 'click');
                    $trackedPropertyId = is_string($meta['tracked_property_id'] ?? null) ? $meta['tracked_property_id'] : null;
                    $isActive = (bool) ($data['activate_created_rules'] ?? false);
                    $existingSortOrder = (int) (SignalInteractionRule::query()->forOwner()->max('sort_order') ?? 0);
                    $created = 0;

                    foreach ($selectedIndexes as $index => $candidateIndex) {
                        $candidate = $candidates[$candidateIndex];
                        $slugBase = Str::slug((string) $candidate['name']);
                        $slug = $this->uniqueSlug($slugBase !== '' ? $slugBase : 'interaction-rule');
                        $candidateSettings = is_array($candidate['settings'] ?? null) ? $candidate['settings'] : [];
                        $candidateSettings['scanner_confidence'] = $candidate['confidence'] ?? null;
                        $candidateSettings['scanner_confidence_note'] = $candidate['confidence_note'] ?? null;

                        SignalInteractionRule::query()->create([
                            'tracked_property_id' => $trackedPropertyId,
                            'name' => (string) $candidate['name'],
                            'slug' => $slug,
                            'trigger_type' => $triggerType,
                            'event_name' => $eventName,
                            'event_category' => $eventCategory,
                            'selector' => is_string($candidate['selector'] ?? null) ? $candidate['selector'] : null,
                            'page_pattern' => is_string($candidate['page_pattern'] ?? null) ? $candidate['page_pattern'] : null,
                            'settings' => $candidateSettings,
                            'sort_order' => $existingSortOrder + $index + 1,
                            'is_active' => $isActive,
                        ]);

                        $created++;
                    }

                    Notification::make()
                        ->title('Interaction rules created from preview')
                        ->body(sprintf('Created %d rule(s).', $created))
                        ->success()
                        ->send();

                    $this->clearScanPreview();
                }),
            Actions\Action::make('scanPage')
                ->label('Scan page')
                ->icon('heroicon-o-magnifying-glass')
                ->modalHeading('Scan page and create draft rules')
                ->modalDescription('We will scan the URL for interactive elements and create disabled draft rules for review.')
                ->form([
                    Select::make('tracked_property_id')
                        ->label('Website or app')
                        ->required()
                        ->searchable()
                        ->options(static fn (): array => TrackedProperty::query()
                            ->forOwner()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all()),
                    Select::make('scan_source')
                        ->label('Scan source')
                        ->options([
                            'url' => 'Live URL',
                            'local_code' => 'Local route + Blade / Livewire source',
                        ])
                        ->default('url')
                        ->required()
                        ->live(),
                    TextInput::make('page_url')
                        ->label('Page URL')
                        ->required(fn (callable $get): bool => (string) ($get('scan_source') ?? 'url') === 'url')
                        ->url()
                        ->placeholder('https://example.test/landing')
                        ->visible(fn (callable $get): bool => (string) ($get('scan_source') ?? 'url') === 'url'),
                    TextInput::make('route_path')
                        ->label('Route path pattern')
                        ->placeholder('/ or /checkout or /offers/*')
                        ->helperText('Used as page pattern when scanning local Blade/Livewire source files.')
                        ->datalist(fn (): array => app(InteractionRuleScanner::class)->discoverRoutePatterns())
                        ->visible(fn (callable $get): bool => (string) ($get('scan_source') ?? 'url') === 'local_code'),
                    Toggle::make('include_blade')
                        ->label('Include Blade files (resources/views)')
                        ->default(true)
                        ->visible(fn (callable $get): bool => (string) ($get('scan_source') ?? 'url') === 'local_code'),
                    Toggle::make('include_livewire')
                        ->label('Include Livewire files (app/Livewire)')
                        ->default(true)
                        ->visible(fn (callable $get): bool => (string) ($get('scan_source') ?? 'url') === 'local_code'),
                    Select::make('trigger_type')
                        ->label('Interaction type')
                        ->options([
                            'click' => 'Click',
                            'accordion' => 'Accordion toggle',
                            'media' => 'Audio / Video',
                            'youtube' => 'YouTube embed click',
                        ])
                        ->default('click')
                        ->required(),
                    TextInput::make('event_name')
                        ->label('Event name for created rules')
                        ->required()
                        ->maxLength(255)
                        ->default('ui.click'),
                    TextInput::make('event_category')
                        ->label('Event category')
                        ->maxLength(100)
                        ->default('engagement'),
                    TextInput::make('max_candidates')
                        ->label('Max candidates')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(200)
                        ->default(25)
                        ->required(),
                    Toggle::make('activate_created_rules')
                        ->label('Activate newly created rules')
                        ->default(false),
                    Toggle::make('preview_only')
                        ->label('Preview candidates only (recommended)')
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    $scanner = app(InteractionRuleScanner::class);

                    try {
                        $scanSource = (string) ($data['scan_source'] ?? 'url');
                        $maxCandidates = max(1, (int) ($data['max_candidates'] ?? 25));

                        if ($scanSource === 'local_code') {
                            $sourcePaths = [];

                            if ((bool) ($data['include_blade'] ?? true)) {
                                $sourcePaths[] = base_path('resources/views');
                            }

                            if ((bool) ($data['include_livewire'] ?? true)) {
                                $sourcePaths[] = base_path('app/Livewire');
                                $sourcePaths[] = base_path('app/Livewire/Volt');
                            }

                            $candidates = $scanner->scanLocalSource(
                                triggerType: (string) $data['trigger_type'],
                                routePath: is_string($data['route_path'] ?? null) ? $data['route_path'] : null,
                                maxCandidates: $maxCandidates,
                                sourcePaths: $sourcePaths,
                            );
                        } else {
                            $candidates = $scanner->scan(
                                pageUrl: (string) $data['page_url'],
                                triggerType: (string) $data['trigger_type'],
                                maxCandidates: $maxCandidates,
                            );
                        }
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Unable to scan candidates')
                            ->body('Please ensure the selected source is reachable and the scan inputs are valid.')
                            ->danger()
                            ->send();

                        return;
                    }

                    if ($candidates === []) {
                        Notification::make()
                            ->title('No candidates found')
                            ->body('No trackable elements were detected for the selected trigger type.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $sourceLabel = (string) (($data['scan_source'] ?? 'url') === 'local_code'
                        ? ((string) ($data['route_path'] ?? '/local-source'))
                        : ((string) ($data['page_url'] ?? 'scanned URL')));

                    $this->storeScanPreview(
                        candidates: $candidates,
                        meta: [
                            'tracked_property_id' => (string) $data['tracked_property_id'],
                            'event_name' => (string) $data['event_name'],
                            'event_category' => (string) ($data['event_category'] ?? 'engagement'),
                            'trigger_type' => (string) $data['trigger_type'],
                            'source_label' => $sourceLabel,
                        ],
                    );

                    $previewOnly = (bool) ($data['preview_only'] ?? false);

                    if ($previewOnly) {
                        $sample = collect($candidates)
                            ->take(8)
                            ->map(static fn (array $candidate): string => sprintf(
                                '• [%s%%] %s (%s)',
                                (string) $candidate['confidence'],
                                (string) $candidate['name'],
                                (string) ($candidate['selector'] ?? 'media default selector'),
                            ))
                            ->implode("\n");

                        Notification::make()
                            ->title('Scan preview ready')
                            ->body(sprintf("Detected %d candidate(s) from %s.\n\n%s\n\nUse \"Create from preview\" to select exactly what to create.", count($candidates), $sourceLabel, $sample))
                            ->info()
                            ->send();

                        return;
                    }

                    $eventName = mb_trim((string) $data['event_name']);
                    $eventCategory = mb_trim((string) ($data['event_category'] ?? ''));
                    $triggerType = (string) $data['trigger_type'];
                    $trackedPropertyId = (string) $data['tracked_property_id'];
                    $isActive = (bool) ($data['activate_created_rules'] ?? false);
                    $existingSortOrder = (int) (SignalInteractionRule::query()->forOwner()->max('sort_order') ?? 0);
                    $created = 0;

                    foreach ($candidates as $index => $candidate) {
                        $slugBase = Str::slug((string) $candidate['name']);
                        $slug = $this->uniqueSlug($slugBase !== '' ? $slugBase : 'interaction-rule');

                        SignalInteractionRule::query()->create([
                            'tracked_property_id' => $trackedPropertyId,
                            'name' => (string) $candidate['name'],
                            'slug' => $slug,
                            'trigger_type' => $triggerType,
                            'event_name' => $eventName,
                            'event_category' => $eventCategory !== '' ? $eventCategory : null,
                            'selector' => $candidate['selector'],
                            'page_pattern' => $candidate['page_pattern'],
                            'settings' => is_array($candidate['settings']) ? $candidate['settings'] : null,
                            'sort_order' => $existingSortOrder + $index + 1,
                            'is_active' => $isActive,
                        ]);

                        $created++;
                    }

                    Notification::make()
                        ->title('Interaction rules created')
                        ->body(sprintf(
                            'Created %d draft rule(s) from %s.',
                            $created,
                            $sourceLabel,
                        ))
                        ->success()
                        ->send();
                }),
            Actions\Action::make('rescanRoute')
                ->label('Rescan route')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->modalHeading('Rescan local route source')
                ->modalDescription('Quickly rescan Blade/Livewire sources for a route path and refresh the preview candidates.')
                ->form([
                    Select::make('tracked_property_id')
                        ->label('Website or app')
                        ->required()
                        ->searchable()
                        ->options(static fn (): array => TrackedProperty::query()
                            ->forOwner()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all()),
                    TextInput::make('route_path')
                        ->label('Route path pattern')
                        ->required()
                        ->placeholder('/, /checkout, /offers/*')
                        ->datalist(fn (): array => app(InteractionRuleScanner::class)->discoverRoutePatterns()),
                    Select::make('trigger_type')
                        ->label('Interaction type')
                        ->options([
                            'click' => 'Click',
                            'accordion' => 'Accordion toggle',
                            'media' => 'Audio / Video',
                            'youtube' => 'YouTube embed click',
                        ])
                        ->default('click')
                        ->required(),
                    TextInput::make('event_name')
                        ->label('Event name')
                        ->required()
                        ->default('ui.click')
                        ->maxLength(255),
                    TextInput::make('event_category')
                        ->label('Event category')
                        ->default('engagement')
                        ->maxLength(100),
                    TextInput::make('max_candidates')
                        ->label('Max candidates')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(200)
                        ->default(25)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $scanner = app(InteractionRuleScanner::class);

                    try {
                        $candidates = $scanner->scanLocalSource(
                            triggerType: (string) $data['trigger_type'],
                            routePath: (string) $data['route_path'],
                            maxCandidates: max(1, (int) ($data['max_candidates'] ?? 25)),
                            sourcePaths: $scanner->defaultSourcePaths(),
                        );
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Unable to rescan route')
                            ->body('Please verify route path and local source availability.')
                            ->danger()
                            ->send();

                        return;
                    }

                    if ($candidates === []) {
                        Notification::make()
                            ->title('No candidates found on rescan')
                            ->warning()
                            ->send();

                        return;
                    }

                    $sourceLabel = (string) $data['route_path'];

                    $this->storeScanPreview(
                        candidates: $candidates,
                        meta: [
                            'tracked_property_id' => (string) $data['tracked_property_id'],
                            'event_name' => (string) $data['event_name'],
                            'event_category' => (string) ($data['event_category'] ?? 'engagement'),
                            'trigger_type' => (string) $data['trigger_type'],
                            'source_label' => $sourceLabel,
                        ],
                    );

                    Notification::make()
                        ->title('Route rescan preview ready')
                        ->body(sprintf('Detected %d candidate(s) for %s. Use "Create from preview" to choose which ones to create.', count($candidates), $sourceLabel))
                        ->info()
                        ->send();
                }),
            Actions\Action::make('clearPreview')
                ->label('Clear preview')
                ->icon('heroicon-o-trash')
                ->color('gray')
                ->visible(fn (): bool => $this->scanPreviewPayload()['candidates'] !== [])
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->clearScanPreview();

                    Notification::make()
                        ->title('Scan preview cleared')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @param  array<string, mixed>  $meta
     */
    private function storeScanPreview(array $candidates, array $meta): void
    {
        Cache::put($this->scanPreviewCacheKey(), [
            'candidates' => $candidates,
            'meta' => $meta,
        ], now()->addMinutes(30));
    }

    /**
     * @return array{candidates: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    private function scanPreviewPayload(): array
    {
        $payload = Cache::get($this->scanPreviewCacheKey());

        if (! is_array($payload)) {
            return ['candidates' => [], 'meta' => []];
        }

        $candidates = is_array($payload['candidates'] ?? null) ? $payload['candidates'] : [];
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

        /** @var list<array<string, mixed>> $candidates */
        return ['candidates' => array_values($candidates), 'meta' => $meta];
    }

    private function clearScanPreview(): void
    {
        Cache::forget($this->scanPreviewCacheKey());
    }

    private function scanPreviewCacheKey(): string
    {
        $userIdentifier = auth()->id();

        return 'filament-signals:interaction-rule-scan-preview:' . (is_scalar($userIdentifier) ? (string) $userIdentifier : 'guest');
    }

    /**
     * @return array<int, string>
     */
    private function previewCandidateOptions(): array
    {
        $candidates = $this->scanPreviewPayload()['candidates'];
        $options = [];

        foreach ($candidates as $index => $candidate) {
            $label = sprintf(
                '[%s%%] %s',
                (string) ($candidate['confidence'] ?? '?'),
                (string) ($candidate['name'] ?? 'Candidate'),
            );

            $options[(string) $index] = $label;
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    private function previewCandidateDescriptions(): array
    {
        $candidates = $this->scanPreviewPayload()['candidates'];
        $descriptions = [];

        foreach ($candidates as $index => $candidate) {
            $selector = (string) ($candidate['selector'] ?? 'media default selector');
            $path = (string) ($candidate['page_pattern'] ?? '*');
            $note = (string) ($candidate['confidence_note'] ?? '');

            $descriptions[(string) $index] = sprintf('%s • path: %s%s', $selector, $path, $note !== '' ? ' • ' . $note : '');
        }

        return $descriptions;
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;

        while (SignalInteractionRule::query()->forOwner()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
