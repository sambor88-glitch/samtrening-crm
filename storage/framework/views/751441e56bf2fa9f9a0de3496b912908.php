<?php if (isset($component)) { $__componentOriginal0ecd9b542bc51e470d24f09770812866 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0ecd9b542bc51e470d24f09770812866 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.skeleton-rows','data' => ['rows' => 5,'height' => 48]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('skeleton-rows'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['rows' => 5,'height' => 48]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0ecd9b542bc51e470d24f09770812866)): ?>
<?php $attributes = $__attributesOriginal0ecd9b542bc51e470d24f09770812866; ?>
<?php unset($__attributesOriginal0ecd9b542bc51e470d24f09770812866); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0ecd9b542bc51e470d24f09770812866)): ?>
<?php $component = $__componentOriginal0ecd9b542bc51e470d24f09770812866; ?>
<?php unset($__componentOriginal0ecd9b542bc51e470d24f09770812866); ?>
<?php endif; ?><?php /**PATH /private/var/folders/sy/nnh7hmnn7q1fnhqmqv89ctq40000gn/T/laravel-bladeUJSy7d.blade.php ENDPATH**/ ?>