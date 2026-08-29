{{-- Overlay đề trong khung video; camera thu PiP góc phải. --}}
@if ($session->hasQuestionSet())
    <div data-live-stage-teach
        class="{{ ! empty($session->stage_teach) ? 'flex' : 'hidden' }} absolute inset-0 z-[25] flex-col overflow-hidden rounded-2xl bg-surface text-on-surface">
        <div data-live-question-panel class="flex min-h-0 flex-1 flex-col overflow-hidden">
            @include('classroom::live.partials.question-panel', ['inStage' => true])
        </div>
    </div>
@endif
