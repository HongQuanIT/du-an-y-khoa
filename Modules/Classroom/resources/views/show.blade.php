@php
    use Illuminate\Support\Str;
    use Modules\Classroom\Enums\LiveSessionStatus;
@endphp

<x-layouts.app :title="$classroom->title">
    <div class="mx-auto max-w-[1200px] space-y-8 p-6 md:p-8">
        <div>
            <a href="{{ route('classroom.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-primary hover:underline">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Classroom
            </a>

            @if (session('success'))
                <div class="mb-4 rounded-xl border border-primary/20 bg-primary/10 px-4 py-3 text-sm text-primary">
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-error/20 bg-error/10 px-4 py-3 text-sm text-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="font-headline-lg text-headline-lg text-on-surface">{{ $classroom->title }}</h1>
                    <p class="mt-2 text-on-surface-variant">
                        Host: <span class="font-medium text-on-surface">{{ $classroom->host?->name }}</span>
                        · {{ $classroom->visibility->label() }}
                        · {{ $classroom->activeMembers->count() }} thành viên
                    </p>
                    @if ($classroom->description)
                        <p class="mt-4 max-w-2xl text-on-surface">{{ $classroom->description }}</p>
                    @endif
                    @if ($canManage && $classroom->join_code)
                        <p class="mt-3 inline-flex items-center gap-2 rounded-lg bg-surface-container-low px-3 py-1.5 text-sm text-on-surface-variant">
                            <span class="material-symbols-outlined text-[18px]">vpn_key</span>
                            Mã tham gia: <strong class="text-on-surface">{{ $classroom->join_code }}</strong>
                        </p>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    @if (! $isMember)
                        <form method="post" action="{{ route('classroom.join', $classroom) }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            @if ($classroom->visibility->value !== 'public')
                                <input type="text" name="join_code" placeholder="Mã tham gia"
                                    class="rounded-xl border-none bg-surface-container-low px-3 py-2 text-sm focus:ring-2 focus:ring-primary"
                                    value="{{ old('join_code') }}">
                            @endif
                            <button type="submit" class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white hover:opacity-90">
                                Tham gia
                            </button>
                        </form>
                    @elseif ($membership?->role_in_class->value !== 'host')
                        <form method="post" action="{{ route('classroom.leave', $classroom) }}"
                            onsubmit="return confirm('Rời lớp này?')">
                            @csrf
                            <button type="submit" class="rounded-xl border border-outline-variant px-4 py-2 text-sm text-on-surface-variant hover:bg-surface-container-low">
                                Rời lớp
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid gap-8 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <section class="rounded-2xl border border-outline-variant bg-surface p-5 shadow-sm">
                    <h2 class="mb-4 font-headline-sm text-headline-sm text-on-surface">Buổi live</h2>

                    @if ($canHostLive)
                        <form method="post" action="{{ route('classroom.sessions.store', $classroom) }}"
                            class="mb-6 space-y-3 rounded-xl bg-surface-container-low p-4">
                            @csrf
                            <div class="grid gap-3 sm:grid-cols-2">
                                <input type="text" name="title" required placeholder="Tiêu đề buổi (vd: Chữa đề Hô hấp)"
                                    class="rounded-xl border-none bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-primary sm:col-span-2"
                                    value="{{ old('title') }}">
                                <input type="datetime-local" name="scheduled_at"
                                    class="rounded-xl border-none bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-primary"
                                    value="{{ old('scheduled_at') }}">
                                <select name="qbank_session_id"
                                    class="rounded-xl border-none bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-primary">
                                    <option value="">— Import phiên luyện —</option>
                                    @foreach ($qbankSessions as $qs)
                                        <option value="{{ $qs->id }}" @selected(old('qbank_session_id') === $qs->id)>
                                            {{ $qs->total }} câu · {{ $qs->created_at?->format('d/m/Y') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @if ($sampleQuestions->isNotEmpty())
                                <details class="text-sm">
                                    <summary class="cursor-pointer text-primary">Chọn câu thủ công (tùy chọn)</summary>
                                    <div class="mt-2 max-h-40 space-y-1 overflow-y-auto">
                                        @foreach ($sampleQuestions as $q)
                                            <label class="flex items-start gap-2 rounded-lg p-1 hover:bg-surface">
                                                <input type="checkbox" name="question_ids[]" value="{{ $q->id }}"
                                                    class="mt-1 rounded border-outline-variant text-primary focus:ring-primary">
                                                <span class="line-clamp-2 text-on-surface">{{ Str::limit($q->stem, 120) }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                            <button type="submit" class="w-full rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white hover:opacity-90 sm:w-auto">
                                Lên lịch buổi chữa đề
                            </button>
                        </form>
                        @unless ($canHostEntitlement)
                            <p class="mb-4 text-sm text-error">Thiếu entitlement host — không thể start live mới.</p>
                        @endunless
                    @endif

                    @if ($classroom->sessions->isEmpty())
                        <p class="text-sm text-on-surface-variant">Chưa có buổi live nào.</p>
                    @else
                        <ul class="divide-y divide-outline-variant">
                            @foreach ($classroom->sessions as $sess)
                                <li class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-medium text-on-surface">{{ $sess->title }}</span>
                                            <span @class([
                                                'rounded-full px-2 py-0.5 text-xs font-semibold',
                                                'bg-error text-white' => $sess->status === LiveSessionStatus::Live,
                                                'bg-primary/15 text-primary' => $sess->status === LiveSessionStatus::Scheduled,
                                                'bg-surface-container text-on-surface-variant' => $sess->status === LiveSessionStatus::Ended,
                                            ])>
                                                {{ $sess->status->label() }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-xs text-on-surface-variant">
                                            @if ($sess->scheduled_at)
                                                Lịch: {{ $sess->scheduled_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                            @endif
                                            @if ($sess->hasQuestionSet())
                                                · {{ count($sess->questionIds()) }} câu
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        @if ($isMember)
                                            <a href="{{ route('classroom.live', [$classroom, $sess]) }}"
                                                class="rounded-lg border border-outline-variant px-3 py-1.5 text-sm hover:bg-surface-container-low">
                                                Vào phòng
                                            </a>
                                        @endif
                                        @if ($canStartLive && $sess->status === LiveSessionStatus::Scheduled)
                                            <form method="post" action="{{ route('classroom.sessions.start', [$classroom, $sess]) }}">
                                                @csrf
                                                <button type="submit" class="rounded-lg bg-error px-3 py-1.5 text-sm font-semibold text-white">
                                                    Bắt đầu live
                                                </button>
                                            </form>
                                        @elseif ($canHostLive && ! ($canStartLive ?? false) && $sess->status === LiveSessionStatus::Scheduled)
                                            <span class="rounded-lg bg-surface-container px-3 py-1.5 text-xs text-on-surface-variant" title="Chờ admin duyệt lớp">
                                                Chờ duyệt lớp
                                            </span>
                                        @endif
                                        @if ($canHostLive && $sess->status === LiveSessionStatus::Live)
                                            <form method="post" action="{{ route('classroom.sessions.end', [$classroom, $sess]) }}"
                                                onsubmit="return confirm('Kết thúc live? Chat sẽ khoá.')">
                                                @csrf
                                                <button type="submit" class="rounded-lg bg-on-surface px-3 py-1.5 text-sm font-semibold text-white">
                                                    Kết thúc
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>

            <aside class="space-y-6">
                <section class="rounded-2xl border border-outline-variant bg-surface p-5 shadow-sm">
                    <h2 class="mb-4 font-headline-sm text-headline-sm text-on-surface">Thành viên</h2>
                    <ul class="max-h-60 space-y-3 overflow-y-auto">
                        @foreach ($classroom->activeMembers as $member)
                            <li class="flex items-center justify-between gap-2 text-sm">
                                <span class="truncate text-on-surface">{{ $member->user?->name }}</span>
                                <span class="shrink-0 text-xs text-on-surface-variant">{{ $member->role_in_class->value }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>

                @if ($canManage)
                    <section class="rounded-2xl border border-outline-variant bg-surface p-5 shadow-sm">
                        <h2 class="mb-3 font-headline-sm text-headline-sm text-on-surface">Mời thành viên</h2>
                        <form method="post" action="{{ route('classroom.invite', $classroom) }}" class="flex gap-2">
                            @csrf
                            <input type="email" name="email" required placeholder="email@medlearn.local"
                                class="min-w-0 flex-1 rounded-xl border-none bg-surface-container-low px-3 py-2 text-sm focus:ring-2 focus:ring-primary">
                            <button type="submit" class="rounded-xl bg-primary px-3 py-2 text-sm font-semibold text-white">Mời</button>
                        </form>
                        <a href="{{ route('classroom.settings', $classroom) }}" class="mt-4 inline-block text-sm text-primary hover:underline">Cài đặt lớp →</a>
                    </section>
                @endif
            </aside>
        </div>
    </div>
</x-layouts.app>
