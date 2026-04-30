<?php
    use AIArmada\FilamentAuthz\Actions\ImpersonateAction;
    use Filament\Facades\Filament;

    $impersonatorGuard = ImpersonateAction::getImpersonatorGuard();
    $currentPanelGuard = Filament::getAuthGuard();
    $shouldShowBanner = ImpersonateAction::isImpersonating()
        && $currentPanelGuard
        && $impersonatorGuard === $currentPanelGuard;

    $user = Filament::auth()->user();
    $displayName = $user?->name ?? 'User';
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($shouldShowBanner): ?>
<style>
    :root {
        --impersonate-banner-height: 42px;
    }
    html {
        margin-top: var(--impersonate-banner-height);
    }
    .fi-topbar-ctn {
        top: var(--impersonate-banner-height) !important;
    }
    div.fi-layout > div > aside.fi-sidebar {
        margin-top: var(--impersonate-banner-height);
        height: calc(100vh - var(--impersonate-banner-height));
    }
    #impersonate-banner {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 50;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        padding: 0.625rem 1rem;
        height: var(--impersonate-banner-height);
        background-color: rgb(245 158 11);
        color: white;
        box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    }
    .dark #impersonate-banner {
        background-color: rgb(217 119 6);
    }
    #impersonate-banner .banner-content {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    #impersonate-banner .banner-text {
        font-size: 0.875rem;
        font-weight: 500;
    }
    #impersonate-banner .banner-link {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        border-radius: 0.5rem;
        background-color: rgb(255 255 255 / 0.2);
        padding: 0.25rem 0.75rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: white;
        text-decoration: none;
        transition: background-color 0.15s;
    }
    #impersonate-banner .banner-link:hover {
        background-color: rgb(255 255 255 / 0.3);
    }
</style>
<div id="impersonate-banner">
    <div class="banner-content">
        <?php if (isset($component)) { $__componentOriginal643fe1b47aec0b76658e1a0200b34b2c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal643fe1b47aec0b76658e1a0200b34b2c = $attributes; } ?>
<?php $component = BladeUI\Icons\Components\Svg::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('heroicon-o-exclamation-triangle'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\BladeUI\Icons\Components\Svg::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['style' => 'width: 1.25rem; height: 1.25rem;']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal643fe1b47aec0b76658e1a0200b34b2c)): ?>
<?php $attributes = $__attributesOriginal643fe1b47aec0b76658e1a0200b34b2c; ?>
<?php unset($__attributesOriginal643fe1b47aec0b76658e1a0200b34b2c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal643fe1b47aec0b76658e1a0200b34b2c)): ?>
<?php $component = $__componentOriginal643fe1b47aec0b76658e1a0200b34b2c; ?>
<?php unset($__componentOriginal643fe1b47aec0b76658e1a0200b34b2c); ?>
<?php endif; ?>
        <span class="banner-text">
            <?php echo e(__('filament-authz::filament-authz.impersonate.banner_message', ['name' => $displayName])); ?>

        </span>
    </div>
    <a href="<?php echo e(route('filament-authz.impersonate.leave')); ?>" class="banner-link">
        <?php if (isset($component)) { $__componentOriginal643fe1b47aec0b76658e1a0200b34b2c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal643fe1b47aec0b76658e1a0200b34b2c = $attributes; } ?>
<?php $component = BladeUI\Icons\Components\Svg::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('heroicon-o-arrow-left-on-rectangle'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\BladeUI\Icons\Components\Svg::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['style' => 'width: 1rem; height: 1rem;']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal643fe1b47aec0b76658e1a0200b34b2c)): ?>
<?php $attributes = $__attributesOriginal643fe1b47aec0b76658e1a0200b34b2c; ?>
<?php unset($__attributesOriginal643fe1b47aec0b76658e1a0200b34b2c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal643fe1b47aec0b76658e1a0200b34b2c)): ?>
<?php $component = $__componentOriginal643fe1b47aec0b76658e1a0200b34b2c; ?>
<?php unset($__componentOriginal643fe1b47aec0b76658e1a0200b34b2c); ?>
<?php endif; ?>
        <?php echo e(__('filament-authz::filament-authz.impersonate.leave')); ?>

    </a>
</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH /Users/saiffil/Herd/commerce/packages/filament-authz/src/../resources/views/components/impersonation-banner.blade.php ENDPATH**/ ?>