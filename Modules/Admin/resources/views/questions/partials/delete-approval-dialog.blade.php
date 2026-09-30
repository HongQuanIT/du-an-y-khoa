@php
    $impact = $deletionImpact ?? [
        'exam_count' => 0,
        'exams' => [],
        'plan_count' => 0,
        'task_count' => 0,
        'plans' => [],
        'bookmark_count' => 0,
        'live_count' => 0,
        'live_sessions' => [],
        'open_session_count' => 0,
        'has_shared_usage' => false,
        'summary' => 'Không thấy câu này trong đề thi, nhiệm vụ kế hoạch đã ghim, bookmark hay buổi live chưa kết thúc.',
    ];
@endphp

<div
    x-show="deleteCheckOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    role="presentation"
    @keydown.escape.window="deleteCheckOpen = false"
>
    <div class="absolute inset-0 bg-on-surface/40" @click="deleteCheckOpen = false" aria-hidden="true"></div>
    <div
        class="relative flex max-h-[min(40rem,calc(100vh-2rem))] w-full max-w-lg flex-col rounded-2xl border border-outline-variant bg-surface"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="delete-impact-title"
        @click.stop
    >
        <div class="border-b border-outline-variant px-6 py-5">
            <h2 id="delete-impact-title" class="font-headline-sm font-bold text-on-surface">Kiểm tra trước khi xóa</h2>
            <p class="mt-2 text-sm leading-6 text-on-surface-variant">{{ $impact['summary'] }}</p>
        </div>

        <div class="space-y-4 overflow-y-auto px-6 py-5 text-sm">
            @if ($impact['has_shared_usage'])
                <p class="leading-6 text-on-surface">
                    Xóa mềm sẽ làm đề đã ghép và nhiệm vụ đã ghim câu không tạo phiên mới được. Bookmark trỏ tới câu này sẽ không mở lại từ ngân hàng. Phiên đang làm vẫn giữ nội dung đã chụp.
                </p>
            @else
                <p class="leading-6 text-on-surface">Có thể duyệt xóa. Câu sẽ biến mất khỏi ngân hàng mới; phiên đã mở vẫn xem lại được.</p>
            @endif

            @if ($impact['exam_count'] > 0)
                <section>
                    <h3 class="font-semibold text-on-surface">Đề thi ({{ $impact['exam_count'] }})</h3>
                    <ul class="mt-1 space-y-1 text-on-surface-variant">
                        @foreach ($impact['exams'] as $exam)
                            <li>{{ $exam['title'] }} · {{ $exam['status'] }}</li>
                        @endforeach
                    </ul>
                    @if ($impact['exam_count'] > count($impact['exams']))
                        <p class="mt-1 text-on-surface-variant">Và {{ $impact['exam_count'] - count($impact['exams']) }} đề khác.</p>
                    @endif
                </section>
            @endif

            @if ($impact['task_count'] > 0)
                <section>
                    <h3 class="font-semibold text-on-surface">Kế hoạch học đã ghim câu ({{ $impact['plan_count'] }} kế hoạch, {{ $impact['task_count'] }} nhiệm vụ)</h3>
                    <ul class="mt-1 space-y-1 text-on-surface-variant">
                        @foreach ($impact['plans'] as $plan)
                            <li>
                                {{ $plan['name'] }} · {{ $plan['owner'] }}
                                @if ($plan['status'] !== '') · {{ $plan['status'] }} @endif
                                · {{ $plan['task_count'] }} nhiệm vụ
                                @if ($plan['next_date']) · từ {{ $plan['next_date'] }} @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($impact['plan_count'] > count($impact['plans']))
                        <p class="mt-1 text-on-surface-variant">Và {{ $impact['plan_count'] - count($impact['plans']) }} kế hoạch khác.</p>
                    @endif
                </section>
            @endif

            @if ($impact['bookmark_count'] > 0)
                <section>
                    <h3 class="font-semibold text-on-surface">Bookmark</h3>
                    <p class="mt-1 text-on-surface-variant">{{ $impact['bookmark_count'] }} lượt lưu của học viên.</p>
                </section>
            @endif

            @if ($impact['live_count'] > 0)
                <section>
                    <h3 class="font-semibold text-on-surface">Buổi live chưa kết thúc ({{ $impact['live_count'] }})</h3>
                    <ul class="mt-1 space-y-1 text-on-surface-variant">
                        @foreach ($impact['live_sessions'] as $session)
                            <li>{{ $session['classroom'] }} · {{ $session['title'] }} · {{ $session['status'] }}</li>
                        @endforeach
                    </ul>
                    @if ($impact['live_count'] > count($impact['live_sessions']))
                        <p class="mt-1 text-on-surface-variant">Và {{ $impact['live_count'] - count($impact['live_sessions']) }} buổi khác.</p>
                    @endif
                </section>
            @endif

            @if ($impact['open_session_count'] > 0)
                <p class="leading-6 text-on-surface-variant">{{ $impact['open_session_count'] }} phiên đang mở vẫn làm tiếp được, vì nội dung đã được chụp lúc bắt đầu phiên.</p>
            @endif
        </div>

        <div class="flex flex-wrap justify-end gap-2 border-t border-outline-variant px-6 py-4">
            <button type="button" @click="deleteCheckOpen = false"
                class="inline-flex h-10 items-center justify-center rounded-xl border border-outline-variant px-4 font-semibold text-on-surface-variant hover:bg-surface-container-low">
                Không xóa
            </button>
            <button type="submit" form="{{ $deleteConfirmFormId ?? 'approve-review-form' }}"
                class="inline-flex h-10 items-center justify-center rounded-xl bg-error px-4 font-semibold text-on-error hover:opacity-90">
                Xác nhận xóa
            </button>
        </div>
    </div>
</div>
