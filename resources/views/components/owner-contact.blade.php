{{-- The way out of every access screen: the owner is the only one who can create, unblock or reset an account. --}}
<p {{ $attributes->class('text-[11px] leading-[1.7] text-muted') }}>
    {{ $slot }}<br>{{ app(App\Domain\Team\Queries\OwnerContact::class)->line() }}
</p>
