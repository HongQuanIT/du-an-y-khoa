{{-- Placeholder buttons: Socialite is not wired up yet. --}}
<div class="relative my-8">
    <div class="absolute inset-0 flex items-center">
        <div class="w-full border-t border-border"></div>
    </div>
    <div class="relative flex justify-center text-label-sm font-label-sm">
        <span class="bg-surface px-4 text-on-surface-variant uppercase tracking-wider">Hoặc tiếp tục với</span>
    </div>
</div>

<div class="grid grid-cols-3 gap-3">
    @foreach (['Google', 'Facebook', 'LinkedIn'] as $provider)
        <button type="button"
            class="flex justify-center items-center py-2.5 border border-border rounded-xl hover:bg-surface-container-low transition-colors active:scale-95"
            title="{{ $provider }}">
            <span class="material-symbols-outlined text-on-surface-variant">public</span>
        </button>
    @endforeach
</div>
