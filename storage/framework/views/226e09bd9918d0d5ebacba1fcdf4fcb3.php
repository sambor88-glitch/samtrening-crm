<?php if (isset($component)) { $__componentOriginal4b5692c1e14c7682e54d7613f3b3ded8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4b5692c1e14c7682e54d7613f3b3ded8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.seg','data' => ['name' => 'filter','options' => $options,'value' => 'archive']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('seg'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'filter','options' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($options),'value' => 'archive']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4b5692c1e14c7682e54d7613f3b3ded8)): ?>
<?php $attributes = $__attributesOriginal4b5692c1e14c7682e54d7613f3b3ded8; ?>
<?php unset($__attributesOriginal4b5692c1e14c7682e54d7613f3b3ded8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4b5692c1e14c7682e54d7613f3b3ded8)): ?>
<?php $component = $__componentOriginal4b5692c1e14c7682e54d7613f3b3ded8; ?>
<?php unset($__componentOriginal4b5692c1e14c7682e54d7613f3b3ded8); ?>
<?php endif; ?><?php /**PATH /private/var/folders/sy/nnh7hmnn7q1fnhqmqv89ctq40000gn/T/laravel-bladeWfklz9.blade.php ENDPATH**/ ?>