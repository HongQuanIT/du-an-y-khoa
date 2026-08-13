@if (session('status'))
    <div role="alert"
        class="flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3">
        <span class="material-symbols-outlined mt-0.5 shrink-0 text-[20px] text-primary">check_circle</span>
        <p class="font-body-md text-body-md text-on-surface">{{ session('status') }}</p>
    </div>
@endif

@if ($errors->any())
    <div role="alert"
        class="rounded-xl border border-error/30 bg-error-container px-4 py-3 font-body-md text-body-md text-on-error-container">
        <ul class="list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
