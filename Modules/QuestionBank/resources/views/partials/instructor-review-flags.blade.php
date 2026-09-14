@php
    $hideNames = $hideNames ?? false;
    $ownFlag = $ownFlag ?? null;
    if ($ownFlag instanceof \Modules\QuestionBank\Enums\ReviewerFlag) {
        $ownFlag = $ownFlag->value;
    }

    if (is_string($ownFlag) && $ownFlag !== '') {
        $ownLabel = match ($ownFlag) {
            'green' => 'Bạn đã gắn cờ xanh',
            'yellow' => 'Bạn đã gắn cờ vàng',
            'red' => 'Bạn đã gắn cờ đỏ',
            default => 'Bạn đã gắn cờ',
        };
        $flags = [
            ['slot' => 0, 'decision' => $ownFlag, 'color' => $ownFlag, 'label' => $ownLabel],
        ];
    } elseif ($hideNames) {
        $flags = [
            ['slot' => 1, 'decision' => null, 'color' => 'white', 'label' => 'Bạn chưa gắn cờ.'],
            ['slot' => 2, 'decision' => null, 'color' => 'white', 'label' => 'Bạn chưa gắn cờ.'],
        ];
    } else {
        $flags = $question->instructorReviewFlags();
    }
@endphp
<div class="inline-flex items-center gap-1" role="img" aria-label="Cờ reviewer">
    @foreach ($flags as $flag)
        @php
            $colorClass = match ($flag['color']) {
                'green' => 'text-emerald-600',
                'yellow' => 'text-amber-500',
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
