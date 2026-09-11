{{-- One toast at a time; a new one replaces the old. From Livewire: $this->dispatch('toast', message: '…'),
     optionally variant: 'error', or an undo: action: ['label' => 'Cofnij', 'event' => 'session-restore',
     'params' => [...]]. After a redirect: ->with('toast', '…'). Timings follow the prototype. --}}
<div role="status" aria-live="polite">
    <div
        x-data="{
            message: null,
            variant: 'success',
            action: null,
            timer: null,
            show(detail) {
                if (typeof detail === 'string') detail = { message: detail };
                clearTimeout(this.timer);
                this.message = detail.message;
                this.variant = detail.variant ?? 'success';
                this.action = detail.action ?? null;
                this.timer = setTimeout(() => this.message = null, this.variant === 'error' ? 6000 : (this.action ? 8000 : 3800));
            },
            runAction() {
                this.$dispatch(this.action.event, this.action.params ?? {});
                clearTimeout(this.timer);
                this.message = null;
            },
        }"
        x-on:toast.window="show($event.detail)"
        @if (session()->has('toast')) x-init="show(@js(session('toast')))" @endif
        x-show="message"
        x-cloak
        class="toast"
        :class="{ 'toast-error': variant === 'error' }"
    >
        <span class="toast-mark" x-text="variant === 'error' ? '!' : '■'" aria-hidden="true"></span>
        <span class="toast-text" x-text="message"></span>
        <template x-if="action">
            <button type="button" class="toast-action" x-text="action.label" x-on:click="runAction()"></button>
        </template>
    </div>
</div>
