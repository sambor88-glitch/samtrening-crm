<?php if (isset($component)) { $__componentOriginale44077d38401ba2b1ed0e0bfffa2dd8b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale44077d38401ba2b1ed0e0bfffa2dd8b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.stat-bar','data' => ['items' => $items]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('stat-bar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['items' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($items)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale44077d38401ba2b1ed0e0bfffa2dd8b)): ?>
<?php $attributes = $__attributesOriginale44077d38401ba2b1ed0e0bfffa2dd8b; ?>
<?php unset($__attributesOriginale44077d38401ba2b1ed0e0bfffa2dd8b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale44077d38401ba2b1ed0e0bfffa2dd8b)): ?>
<?php $component = $__componentOriginale44077d38401ba2b1ed0e0bfffa2dd8b; ?>
<?php unset($__componentOriginale44077d38401ba2b1ed0e0bfffa2dd8b); ?>
<?php endif; ?><?php /**PATH /private/var/folders/sy/nnh7hmnn7q1fnhqmqv89ctq40000gn/T/laravel-blade8CyQfF.blade.php ENDPATH**/ ?>