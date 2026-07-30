@if ($errors->any())
    <div class="mb-6 p-4 rounded-xl bg-error-container text-on-error-container text-body-sm font-body-sm" role="alert">
        @if ($errors->count() === 1)
            {{ $errors->first() }}
        @else
            <ul class="space-y-1 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
