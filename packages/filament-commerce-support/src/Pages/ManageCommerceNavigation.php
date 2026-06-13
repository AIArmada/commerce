<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport\Pages;

use AIArmada\FilamentCommerceSupport\Settings\CommerceNavigationSettings;
use AIArmada\CommerceSupport\Support\Filament\CommerceNavigation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Spatie\LaravelSettings\Exceptions\MissingSettings;
use UnitEnum;

class ManageCommerceNavigation extends Page
{
    public ?array $data = [];

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-bars-3';

    protected static string | UnitEnum | null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 100;

    public static function getNavigationLabel(): string
    {
        return __('Navigation');
    }

    public function getTitle(): string
    {
        return __('Navigation Manager');
    }

    public function mount(): void
    {
        $settings = $this->resolveSettings();

        $this->data = [
            'groups' => $this->normalizeGroupsForForm($settings->groups),
            'overrides' => $this->normalizeOverridesForForm($settings->overrides),
        ];

        $this->getSchema('form')?->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Repeater::make('groups')
                    ->label(__('Navigation Groups'))
                    ->schema([
                        Select::make('group_key')
                            ->label(__('Group Key'))
                            ->options(fn (): array => $this->getGroupKeyOptions())
                            ->searchable()
                            ->required(),

                        TextInput::make('label')
                            ->label(__('Label'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('icon')
                            ->label(__('Icon'))
                            ->helperText(__('Heroicon name, e.g. heroicon-o-shopping-bag'))
                            ->maxLength(255),

                        TextInput::make('sort')
                            ->label(__('Sort Order'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(9999),

                        Toggle::make('collapsible')
                            ->label(__('Collapsible')),

                        Toggle::make('collapsed')
                            ->label(__('Collapsed by Default')),
                    ])
                    ->collapsible()
                    ->defaultItems(0)
                    ->addActionLabel(__('Add Group'))
                    ->reorderable()
                    ->columns(2),

                Repeater::make('overrides')
                    ->label(__('Item Overrides'))
                    ->schema([
                        Select::make('component')
                            ->label(__('Component Class'))
                            ->options(fn (): array => $this->getComponentOptions())
                            ->searchable()
                            ->required()
                            ->helperText(__('FQCN of the resource or page'))
                            ->columnSpanFull(),

                        Toggle::make('hidden')
                            ->label(__('Hidden')),

                        Select::make('group')
                            ->label(__('Group'))
                            ->searchable()
                            ->options(fn (): array => $this->getGroupOptions()),

                        TextInput::make('sort')
                            ->label(__('Sort Order'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(9999),

                        TextInput::make('parent_item')
                            ->label(__('Parent Item'))
                            ->helperText(__('Label of the parent navigation item (if any)'))
                            ->maxLength(255),
                    ])
                    ->collapsible()
                    ->defaultItems(0)
                    ->addActionLabel(__('Add Override'))
                    ->reorderable()
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $settings = $this->resolveSettings();

        $settings->groups = $this->denormalizeGroupsFromForm($this->data['groups'] ?? []);
        $settings->overrides = $this->denormalizeOverridesFromForm($this->data['overrides'] ?? []);

        $settings->save();

        Notification::make()
            ->title(__('Navigation configuration saved.'))
            ->success()
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('Save'))
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action('save'),
        ];
    }

    protected function resolveSettings(): CommerceNavigationSettings
    {
        try {
            return app(CommerceNavigationSettings::class);
        } catch (MissingSettings) {
            $settings = new CommerceNavigationSettings([
                'groups' => [],
                'overrides' => [],
            ]);
            $settings->save();

            app()->forgetInstance(CommerceNavigationSettings::class);

            return app(CommerceNavigationSettings::class);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    private function normalizeGroupsForForm(array $groups): array
    {
        $items = [];

        foreach ($groups as $key => $config) {
            $items[] = [
                'group_key' => $key,
                'label' => $config['label'] ?? $key,
                'icon' => $config['icon'] ?? '',
                'sort' => $config['sort'] ?? 0,
                'collapsible' => $config['collapsible'] ?? true,
                'collapsed' => $config['collapsed'] ?? false,
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, array<string, mixed>>  $overrides
     * @return list<array<string, mixed>>
     */
    private function normalizeOverridesForForm(array $overrides): array
    {
        $items = [];

        foreach ($overrides as $component => $config) {
            $items[] = [
                'component' => $component,
                'hidden' => $config['hidden'] ?? false,
                'group' => $config['group'] ?? '',
                'sort' => $config['sort'] ?? 0,
                'parent_item' => $config['parent_item'] ?? '',
            ];
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $formGroups
     * @return array<string, array<string, mixed>>
     */
    private function denormalizeGroupsFromForm(array $formGroups): array
    {
        $groups = [];

        foreach ($formGroups as $item) {
            $key = $item['group_key'] ?? '';

            if ($key === '') {
                continue;
            }

            $config = [];

            if (isset($item['label']) && $item['label'] !== '') {
                $config['label'] = $item['label'];
            }

            if (isset($item['icon']) && $item['icon'] !== '') {
                $config['icon'] = $item['icon'];
            }

            if (isset($item['sort']) && $item['sort'] !== '') {
                $config['sort'] = (int) $item['sort'];
            }

            if (isset($item['collapsible'])) {
                $config['collapsible'] = (bool) $item['collapsible'];
            }

            if (isset($item['collapsed'])) {
                $config['collapsed'] = (bool) $item['collapsed'];
            }

            $groups[$key] = $config;
        }

        return $groups;
    }

    /**
     * @param  list<array<string, mixed>>  $formOverrides
     * @return array<string, array<string, mixed>>
     */
    private function denormalizeOverridesFromForm(array $formOverrides): array
    {
        $overrides = [];

        foreach ($formOverrides as $item) {
            $component = $item['component'] ?? '';

            if ($component === '') {
                continue;
            }

            $config = [];

            if (isset($item['hidden'])) {
                $config['hidden'] = (bool) $item['hidden'];
            }

            if (isset($item['group']) && $item['group'] !== '') {
                $config['group'] = $item['group'];
            }

            if (isset($item['sort']) && $item['sort'] !== '') {
                $config['sort'] = (int) $item['sort'];
            }

            if (isset($item['parent_item']) && $item['parent_item'] !== '') {
                $config['parent_item'] = $item['parent_item'];
            }

            $overrides[$component] = $config;
        }

        return $overrides;
    }

    /**
     * @return array<string, string>
     */
    private function getComponentOptions(): array
    {
        $panel = Filament::getCurrentOrDefaultPanel();
        $options = [];

        foreach ($panel->getResources() as $resource) {
            $class = is_string($resource) ? $resource : $resource::class;
            $label = $class::getNavigationLabel();
            $group = $class::getNavigationGroup();
            $groupPrefix = $group ? "[{$group}] " : '';
            $options[$class] = "{$groupPrefix}[Resource] {$label} — {$class}";
        }

        foreach ($panel->getPages() as $page) {
            $class = is_string($page) ? $page : $page::class;
            $label = $class::getNavigationLabel();
            $group = $class::getNavigationGroup();
            $groupPrefix = $group ? "[{$group}] " : '';
            $options[$class] = "{$groupPrefix}[Page] {$label} — {$class}";
        }

        foreach ($panel->getPageConfigurations() as $configuration) {
            $page = $configuration->getPage();
            $class = $page::class;
            $label = $page::getNavigationLabel();
            $group = $page::getNavigationGroup();
            $groupPrefix = $group ? "[{$group}] " : '';
            $options[$class] = "{$groupPrefix}[Page] {$label} — {$class}";
        }

        ksort($options);

        return $options;
    }

    private function getGroupKeyOptions(): array
    {
        $defaultKeys = [
            'Addressing' => 'Addressing',
            'Affiliate Network' => 'Affiliate Network',
            'Affiliate Portal' => 'Affiliate Portal',
            'Authz' => 'Authz',
            'Billing' => 'Billing',
            'Catalog' => 'Catalog',
            'CHIP Operations' => 'CHIP Operations',
            'Contacting' => 'Contacting',
            'CRM' => 'CRM',
            'Documents' => 'Documents',
            'E-commerce' => 'E-commerce',
            'Engagement' => 'Engagement',
            'Events' => 'Events',
            'Feedback' => 'Feedback',
            'Growth' => 'Growth',
            'Insights' => 'Insights',
            'Inventory' => 'Inventory',
            'Marketing' => 'Marketing',
            'Payments' => 'Payments',
            'Pricing' => 'Pricing',
            'Sales' => 'Sales',
            'Settings' => 'Settings',
            'Shipping' => 'Shipping',
            'Tax' => 'Tax',
            'Vouchers & Discounts' => 'Vouchers & Discounts',
        ];

        $keys = [];

        foreach (config('commerce-support.filament.navigation.groups', []) as $key => $config) {
            $label = is_array($config) ? ($config['label'] ?? $key) : $key;
            $keys[$key] = $label;
        }

        $settings = $this->resolveSettings();

        foreach ($settings->groups as $key => $config) {
            $label = $config['label'] ?? $key;
            $keys[$key] = $label;
        }

        $keys = array_merge($defaultKeys, $keys);

        ksort($keys);

        return $keys;
    }

    /**
     * @return array<string, string>
     */
    private function getGroupOptions(): array
    {
        return $this->getGroupKeyOptions();
    }
}
