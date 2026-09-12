@php
    $hideNames = $hideNames ?? false;
    $flags = $hideNames
        ? [
            ['slot' => 1, 'decision' => null, 'color' => 'white', 'label' => 'Bạn chưa gửi phiếu. Cần 2 giảng viên.'],
            ['slot' => 2, 'decision' => null, 'color' => 'white', 'label' => 'Bạn chưa gửi phiếu. Cần 2 giảng viên.'],
        ]
        : $question->instructorReviewFlags();
@endphp
<div class="inline-flex items-center gap-1" role="img" aria-label="Phiếu duyệt giảng viên">
    @foreach ($flags as $flag)
        @php
            $colorClass = match ($flag['color']) {
                'green' => 'text-emerald-600',
                'red' => 'text-red-600',
                default => 'text-slate-400',
            };
            $filled = ($flag['decision'] ?? null) !== null;
            $label = $flag['label'];
        @endphp
        <span class="material-symbols-outlined text-[20px] {{ $colorClass }}"
            @if ($filled) style="font-variation-settings: 'FILL' 1;" @endif
            title="{{ $label }}"
            aria-label="{{ $label }}">flag</span>
    @endforeach
</div>
