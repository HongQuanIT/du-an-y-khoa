<button
    {{ $attributes->merge([
        'type' => 'submit',
        'class' =>
            'w-full bg-primary-container text-white py-3 rounded-xl font-label-md text-label-md flex items-center justify-center gap-3 hover:opacity-90 active:scale-[0.98] transition-all shadow-md',
    ]) }}>
    {{ $slot }}
</button>
