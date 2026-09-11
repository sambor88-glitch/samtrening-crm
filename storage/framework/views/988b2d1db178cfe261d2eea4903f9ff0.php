<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

        <title><?php echo e($title ? $title.' — ' : ''); ?><?php echo e(config('app.name')); ?></title>

        <?php echo app('Illuminate\Foundation\Vite')->fonts(); ?>
        <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
        <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>

    </head>
    <body>
        <?php if (isset($component)) { $__componentOriginale74146fa7a2dcfa17eca1de135a55cea = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale74146fa7a2dcfa17eca1de135a55cea = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layout.top-bar','data' => ['user' => auth()->user(),'admin' => $admin]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layout.top-bar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(auth()->user()),'admin' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($admin)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale74146fa7a2dcfa17eca1de135a55cea)): ?>
<?php $attributes = $__attributesOriginale74146fa7a2dcfa17eca1de135a55cea; ?>
<?php unset($__attributesOriginale74146fa7a2dcfa17eca1de135a55cea); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale74146fa7a2dcfa17eca1de135a55cea)): ?>
<?php $component = $__componentOriginale74146fa7a2dcfa17eca1de135a55cea; ?>
<?php unset($__componentOriginale74146fa7a2dcfa17eca1de135a55cea); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginalf0fd02313a0aabf1e6674de77b2443fd = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf0fd02313a0aabf1e6674de77b2443fd = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layout.app-nav','data' => ['admin' => $admin,'navigation' => $navigation]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layout.app-nav'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['admin' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($admin),'navigation' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($navigation)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf0fd02313a0aabf1e6674de77b2443fd)): ?>
<?php $attributes = $__attributesOriginalf0fd02313a0aabf1e6674de77b2443fd; ?>
<?php unset($__attributesOriginalf0fd02313a0aabf1e6674de77b2443fd); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf0fd02313a0aabf1e6674de77b2443fd)): ?>
<?php $component = $__componentOriginalf0fd02313a0aabf1e6674de77b2443fd; ?>
<?php unset($__componentOriginalf0fd02313a0aabf1e6674de77b2443fd); ?>
<?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tickerEnabled): ?>
            <?php if (isset($component)) { $__componentOriginalc1be1ab669523881de6478b11ed461af = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc1be1ab669523881de6478b11ed461af = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layout.ticker','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layout.ticker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc1be1ab669523881de6478b11ed461af)): ?>
<?php $attributes = $__attributesOriginalc1be1ab669523881de6478b11ed461af; ?>
<?php unset($__attributesOriginalc1be1ab669523881de6478b11ed461af); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc1be1ab669523881de6478b11ed461af)): ?>
<?php $component = $__componentOriginalc1be1ab669523881de6478b11ed461af; ?>
<?php unset($__componentOriginalc1be1ab669523881de6478b11ed461af); ?>
<?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <main class="mx-auto max-w-[1240px] px-4 pt-8 pb-20">
            <?php echo e($slot); ?>

        </main>

        <?php if (isset($component)) { $__componentOriginal7cfab914afdd05940201ca0b2cbc009b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7cfab914afdd05940201ca0b2cbc009b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.toast','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('toast'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7cfab914afdd05940201ca0b2cbc009b)): ?>
<?php $attributes = $__attributesOriginal7cfab914afdd05940201ca0b2cbc009b; ?>
<?php unset($__attributesOriginal7cfab914afdd05940201ca0b2cbc009b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7cfab914afdd05940201ca0b2cbc009b)): ?>
<?php $component = $__componentOriginal7cfab914afdd05940201ca0b2cbc009b; ?>
<?php unset($__componentOriginal7cfab914afdd05940201ca0b2cbc009b); ?>
<?php endif; ?>

        <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>

    </body>
</html>
<?php /**PATH /Users/maciejsamborski/Desktop/repo-samtrening-crm/resources/views/layouts/app.blade.php ENDPATH**/ ?>