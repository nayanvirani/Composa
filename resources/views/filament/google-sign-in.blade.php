<div style="margin-top: 1rem; text-align: center">
    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;color:#78716c;font-size:.8rem">
        <span style="flex:1;height:1px;background:#e7e5e4"></span>or<span style="flex:1;height:1px;background:#e7e5e4"></span>
    </div>
    <x-filament::button tag="a" :href="route('admin.google.redirect')" color="gray" outlined style="width:100%">
        Continue with Google
    </x-filament::button>
    @if (session('google_error'))
        <p style="margin-top:.75rem;color:#b4532a;font-size:.85rem">{{ session('google_error') }}</p>
    @endif
</div>
