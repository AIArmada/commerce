<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Discovers Filament entities (Resources, Pages, Widgets) and generates permission names.
 */
class EntityDiscoveryService
{
    /**
     * @return Collection<int, array{type: string, class: class-string, permission: string, label: string}>
     */
    public function discover(?Panel $panel = null): Collection
    {
        $panel ??= Filament::getCurrentPanel();

        if ($panel === null) {
            return collect();
        }

        return collect()
            ->merge($this->discoverResources($panel))
            ->merge($this->discoverPages($panel))
            ->merge($this->discoverWidgets($panel));
    }

    /**
     * @return Collection<int, array{type: string, class: class-string, permission: string, label: string}>
     */
    public function discoverResources(?Panel $panel = null): Collection
    {
        $panel ??= Filament::getCurrentPanel();

        if ($panel === null) {
            return collect();
        }

        $excludedResources = config('filament-authz.resources.exclude', []);
        $separator = config('filament-authz.permissions.separator', '.');

        return collect($panel->getResources())
            ->filter(fn (string $resource): bool => ! in_array($resource, $excludedResources, true))
            ->flatMap(function (string $resource) use ($separator): array {
                /** @var class-string<resource> $resource */
                $key = $this->getResourceKey($resource);
                $label = $this->getResourceLabel($resource);

                return collect($this->getResourceActions())
                    ->map(fn (string $action): array => [
                        'type' => 'resource',
                        'class' => $resource,
                        'permission' => $key . $separator . $action,
                        'label' => $label . ' - ' . str($action)->headline()->toString(),
                    ])
                    ->all();
            })
            ->values();
    }

    /**
     * @return Collection<int, array{type: string, class: class-string, permission: string, label: string}>
     */
    public function discoverPages(?Panel $panel = null): Collection
    {
        $panel ??= Filament::getCurrentPanel();

        if ($panel === null) {
            return collect();
        }

        $excludedPages = config('filament-authz.pages.exclude', []);
        $prefix = config('filament-authz.pages.prefix', 'page');
        $separator = config('filament-authz.permissions.separator', '.');

        return collect($panel->getPages())
            ->filter(fn (string $page): bool => ! in_array($page, $excludedPages, true))
            ->map(function (string $page) use ($prefix, $separator): array {
                /** @var class-string<Page> $page */
                $key = str(class_basename($page))->kebab()->toString();

                return [
                    'type' => 'page',
                    'class' => $page,
                    'permission' => $prefix . $separator . $key,
                    'label' => str(class_basename($page))->headline()->toString(),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array{type: string, class: class-string, permission: string, label: string}>
     */
    public function discoverWidgets(?Panel $panel = null): Collection
    {
        $panel ??= Filament::getCurrentPanel();

        if ($panel === null) {
            return collect();
        }

        $excludedWidgets = config('filament-authz.widgets.exclude', []);
        $prefix = config('filament-authz.widgets.prefix', 'widget');
        $separator = config('filament-authz.permissions.separator', '.');

        return collect($panel->getWidgets())
            ->filter(fn (string $widget): bool => ! in_array($widget, $excludedWidgets, true))
            ->map(function (string $widget) use ($prefix, $separator): array {
                /** @var class-string<Widget> $widget */
                $key = str(class_basename($widget))->kebab()->toString();

                return [
                    'type' => 'widget',
                    'class' => $widget,
                    'permission' => $prefix . $separator . $key,
                    'label' => str(class_basename($widget))->headline()->toString(),
                ];
            })
            ->values();
    }

    /**
     * Get all discovered permission names.
     *
     * @return list<string>
     */
    public function getPermissions(?Panel $panel = null): array
    {
        return $this->discover($panel)
            ->pluck('permission')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    protected function getResourceActions(): array
    {
        return config('filament-authz.resources.actions', [
            'viewAny',
            'view',
            'create',
            'update',
            'delete',
            'restore',
            'forceDelete',
        ]);
    }

    /**
     * @param  class-string<resource>  $resource
     */
    protected function getResourceKey(string $resource): string
    {
        $subject = config('filament-authz.resources.subject', 'model');

        if ($subject === 'model' && method_exists($resource, 'getModel')) {
            return str(class_basename($resource::getModel()))->kebab()->toString();
        }

        return str(class_basename($resource))
            ->beforeLast('Resource')
            ->kebab()
            ->toString();
    }

    /**
     * @param  class-string<resource>  $resource
     */
    protected function getResourceLabel(string $resource): string
    {
        if (method_exists($resource, 'getModelLabel')) {
            return $resource::getModelLabel();
        }

        return str(class_basename($resource))
            ->beforeLast('Resource')
            ->headline()
            ->toString();
    }
}
