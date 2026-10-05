<x-layouts.admin title="Cách chọn câu thích ứng">
    <x-admin.page-header title="Cách hệ thống chọn câu cho học viên"
        description="Mỗi lần tạo phiên là một bảng riêng. Pipeline V2 luôn theo thứ tự: ① Lọc → ② Phân nhóm → ③ Phân suất.">
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-outline-variant bg-surface px-3 py-1.5 font-label-sm text-label-sm text-on-surface-variant">
                <span class="material-symbols-outlined text-[16px]">schedule</span>
                Tải lại trang sau khi tạo phiên mới
            </span>
            @if ($log_ready)
                <form method="post" action="{{ route('admin.adaptive-briefing.destroy') }}"
                    onsubmit="return confirm('Xóa toàn bộ log thích ứng? Không khôi phục được.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-full border border-error/40 bg-surface px-3 py-1.5 font-label-sm font-semibold text-error hover:bg-error/10">
                        <span class="material-symbols-outlined text-[16px]">delete</span>
                        Xóa log
                    </button>
                </form>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    @if (! $log_ready)
        <div class="rounded-xl border border-dashed border-outline-variant bg-surface px-6 py-12 text-center">
            <h2 class="font-title-md text-on-surface">Chưa có lần chọn câu nào</h2>
            <p class="mx-auto mt-2 max-w-lg font-body-sm text-on-surface-variant">
                Hãy tạo một phiên luyện thích ứng bằng tài khoản học viên. Quyết định của hệ thống sẽ hiện tại đây.
            </p>
        </div>
    @elseif (count($sessions) === 0)
        <div class="rounded-xl border border-dashed border-outline-variant bg-surface px-6 py-12 text-center">
            <h2 class="font-title-md text-on-surface">Nhật ký còn trống</h2>
            <p class="mx-auto mt-2 max-w-lg font-body-sm text-on-surface-variant">
                Tạo phiên thích ứng xong, tải lại trang này. Nên xóa log cũ nếu còn phiên thuật toán trước.
            </p>
        </div>
    @else
        <div class="space-y-5">
            @foreach ($sessions as $session)
                <article class="rounded-xl border border-outline-variant bg-surface p-5 sm:p-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-label-sm text-on-surface-variant">{{ $session['when'] }}</p>
                        <span @class([
                            'rounded-full px-2.5 py-1 font-label-sm',
                            'bg-rose-500/10 text-rose-700' => $session['focus_tone'] === 'rose',
                            'bg-amber-500/15 text-amber-800' => $session['focus_tone'] === 'amber',
                            'bg-sky-500/10 text-sky-800' => $session['focus_tone'] === 'sky',
                        ])>{{ $session['focus'] }}</span>
                        @if (($session['pipeline'] ?? 'v1') === 'v2')
                            <span class="rounded-full bg-emerald-500/10 px-2.5 py-1 font-label-sm text-emerald-800">V2 · {{ $session['pipeline_label'] ?? 'Lọc → Phân nhóm → Phân suất' }}</span>
                        @else
                            <span class="rounded-full bg-outline-variant/40 px-2.5 py-1 font-label-sm text-on-surface-variant">V1 · điểm ưu tiên</span>
                        @endif
                    </div>

                    <h2 class="mt-3 font-title-md text-on-surface">{{ $session['headline'] }}</h2>
                    <p class="mt-2 max-w-3xl font-body-sm text-on-surface-variant">{{ $session['summary'] }}</p>

                    @if (($session['pipeline'] ?? 'v1') === 'v2' && count($session['stages'] ?? []) > 0)
                        <h3 class="mt-6 font-label-md text-on-surface">Pipeline theo thứ tự thực thi</h3>
                        <ol class="mt-3 grid gap-3 lg:grid-cols-3">
                            @foreach ($session['stages'] as $stage)
                                <li class="rounded-lg border border-outline-variant bg-surface-container-low px-4 py-3">
                                    <p class="font-label-md font-semibold text-on-surface">{{ $stage['title'] }}</p>
                                    <p class="mt-2 font-body-sm text-on-surface-variant">{{ $stage['body'] }}</p>
                                    <ul class="mt-3 list-disc space-y-1 pl-4 font-body-sm text-on-surface-variant">
                                        @foreach ($stage['items'] as $item)
                                            <li>{{ $item }}</li>
                                        @endforeach
                                    </ul>
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    @if (count($session['stats']) > 0)
                        <dl class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            @foreach ($session['stats'] as $stat)
                                <div class="rounded-lg bg-surface-container-low px-3 py-3">
                                    <dt class="font-label-sm text-on-surface-variant">{{ $stat['label'] }}</dt>
                                    <dd class="mt-1 font-title-md text-on-surface">{{ $stat['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    @if (count($session['table']) > 0)
                        <h3 class="mt-6 font-label-md text-on-surface">Bảng chọn câu của phiên này</h3>
                        @if (($session['pipeline'] ?? 'v1') === 'v2')
                            <p class="mt-1 font-body-sm text-on-surface-variant">Mỗi câu thuộc một nhóm (Yếu / Sắp quên / Mới / Lấp) kèm lý do chọn và mốc đến hạn (due).</p>
                            <div class="mt-3 overflow-x-auto rounded-lg border border-outline-variant">
                                <table class="w-full min-w-[1100px] border-collapse text-left">
                                    <thead>
                                        <tr class="border-b border-outline-variant bg-surface-container-low font-label-sm text-on-surface-variant">
                                            <th class="px-3 py-3 text-right font-medium" scope="col">STT</th>
                                            <th class="px-3 py-3 font-medium" scope="col">Mã câu</th>
                                            <th class="bg-primary/5 px-3 py-3 font-semibold text-on-surface" scope="col">Nhóm</th>
                                            <th class="bg-primary/5 px-3 py-3 font-semibold text-on-surface" scope="col">Lý do</th>
                                            <th class="bg-primary/5 px-3 py-3 font-semibold text-on-surface" scope="col">Due</th>
                                            <th class="px-3 py-3 text-right font-medium" scope="col">Độ yếu</th>
                                            <th class="px-3 py-3 text-right font-medium" scope="col">Ngày từ lần chấm</th>
                                            <th class="px-3 py-3 text-right font-medium" scope="col">Còn nhớ (R)</th>
                                            <th class="px-3 py-3 text-right font-medium" scope="col">Độ bền S</th>
                                        </tr>
                                    </thead>
                                    <tbody class="font-body-sm text-on-surface">
                                        @foreach ($session['table'] as $row)
                                            <tr class="border-b border-outline-variant/70 last:border-0">
                                                <td class="px-3 py-3 text-right tabular-nums text-on-surface-variant">{{ $row['rank'] }}</td>
                                                <td class="px-3 py-3 font-label-md">{{ $row['code'] }}</td>
                                                <td class="bg-primary/5 px-3 py-3 font-label-md">{{ $row['bucket'] }}</td>
                                                <td class="bg-primary/5 px-3 py-3">{{ $row['reason'] }}</td>
                                                <td class="bg-primary/5 px-3 py-3 tabular-nums">{{ $row['due'] ?? '—' }}</td>
                                                <td class="px-3 py-3 text-right tabular-nums">{{ $row['weakness'] }}</td>
                                                <td class="px-3 py-3 text-right tabular-nums">{{ $row['days'] }}</td>
                                                <td class="px-3 py-3 text-right tabular-nums">{{ $row['retention'] }}</td>
                                                <td class="px-3 py-3 text-right tabular-nums">{{ $row['stability'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if (count($session['graded'] ?? []) > 0)
                                <h3 class="mt-6 font-label-md text-on-surface">Bảng chấm điểm sau phiên</h3>
                                <p class="mt-1 font-body-sm text-on-surface-variant">Đối chiếu đúng/sai, S trước → sau, hạn ôn, độ yếu cửa sổ 5. Đ/S trong cột gần nhất = Đúng/Sai.</p>
                                <div class="mt-3 overflow-x-auto rounded-lg border border-outline-variant">
                                    <table class="w-full min-w-[1280px] border-collapse text-left">
                                        <thead>
                                            <tr class="border-b border-outline-variant bg-surface-container-low font-label-sm text-on-surface-variant">
                                                <th class="px-3 py-3 text-right font-medium" scope="col">STT</th>
                                                <th class="px-3 py-3 font-medium" scope="col">Mã câu</th>
                                                <th class="bg-primary/5 px-3 py-3 font-semibold text-on-surface" scope="col">Kết quả</th>
                                                <th class="bg-primary/5 px-3 py-3 font-semibold text-on-surface" scope="col">S trước</th>
                                                <th class="bg-primary/5 px-3 py-3 font-semibold text-on-surface" scope="col">S sau</th>
                                                <th class="px-3 py-3 font-medium" scope="col">t / hạn</th>
                                                <th class="px-3 py-3 font-medium" scope="col">Due mới</th>
                                                <th class="px-3 py-3 text-right font-medium" scope="col">W sau</th>
                                                <th class="px-3 py-3 text-right font-medium" scope="col">Streak</th>
                                                <th class="px-3 py-3 font-medium" scope="col">5 lần gần</th>
                                                <th class="px-3 py-3 text-right font-medium" scope="col">Giây</th>
                                                <th class="px-3 py-3 font-medium" scope="col">Ghi chú</th>
                                            </tr>
                                        </thead>
                                        <tbody class="font-body-sm text-on-surface">
                                            @foreach ($session['graded'] as $row)
                                                <tr class="border-b border-outline-variant/70 last:border-0">
                                                    <td class="px-3 py-3 text-right tabular-nums text-on-surface-variant">{{ $row['rank'] }}</td>
                                                    <td class="px-3 py-3 font-label-md">{{ $row['code'] }}</td>
                                                    <td class="bg-primary/5 px-3 py-3 font-label-md">{{ $row['result'] }}</td>
                                                    <td class="bg-primary/5 px-3 py-3 text-right tabular-nums">{{ $row['s_before'] }}</td>
                                                    <td class="bg-primary/5 px-3 py-3 text-right tabular-nums">{{ $row['s_after'] }}</td>
                                                    <td class="px-3 py-3 tabular-nums">{{ $row['t_days'] }} · {{ $row['due_state'] }}</td>
                                                    <td class="px-3 py-3 tabular-nums">{{ $row['due'] }}</td>
                                                    <td class="px-3 py-3 text-right tabular-nums">{{ $row['weakness'] }}</td>
                                                    <td class="px-3 py-3 text-right tabular-nums">{{ $row['streak'] }}</td>
                                                    <td class="px-3 py-3 font-label-md">{{ $row['recent'] }}</td>
                                                    <td class="px-3 py-3 text-right tabular-nums">{{ $row['time'] }}</td>
                                                    <td class="px-3 py-3">{{ $row['note'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                            @if (count($session['resting'] ?? []) > 0)
                                <h3 class="mt-6 font-label-md text-on-surface">Câu đang nghỉ (không vào phiên)</h3>
                                <p class="mt-1 font-body-sm text-on-surface-variant">Thrash hoặc nghỉ serve 20 giờ. Due = mốc ôn theo độ bền S.</p>
                                <div class="mt-3 overflow-x-auto rounded-lg border border-outline-variant">
                                    <table class="w-full min-w-[1040px] border-collapse text-left">
                                        <thead>
                                            <tr class="border-b border-outline-variant bg-surface-container-low font-label-sm text-on-surface-variant">
                                                <th class="px-3 py-3 font-medium" scope="col">Mã câu</th>
                                                <th class="px-3 py-3 font-medium" scope="col">Loại nghỉ</th>
                                                <th class="px-3 py-3 font-medium" scope="col">Chi tiết</th>
                                                <th class="px-3 py-3 font-medium" scope="col">Hết nghỉ</th>
                                                <th class="bg-primary/5 px-3 py-3 font-semibold text-on-surface" scope="col">Due</th>
                                                <th class="px-3 py-3 text-right font-medium" scope="col">Còn nhớ (R)</th>
                                                <th class="px-3 py-3 text-right font-medium" scope="col">Độ bền S</th>
                                            </tr>
                                        </thead>
                                        <tbody class="font-body-sm text-on-surface">
                                            @foreach ($session['resting'] as $row)
                                                <tr class="border-b border-outline-variant/70 last:border-0">
                                                    <td class="px-3 py-3 font-label-md">{{ $row['code'] }}</td>
                                                    <td class="px-3 py-3">{{ $row['reason'] }}</td>
                                                    <td class="px-3 py-3">{{ $row['detail'] }}</td>
                                                    <td class="px-3 py-3 tabular-nums">{{ $row['rest_until'] }}</td>
                                                    <td class="bg-primary/5 px-3 py-3 tabular-nums">{{ $row['due'] }}</td>
                                                    <td class="px-3 py-3 text-right tabular-nums">{{ $row['retention'] }}</td>
                                                    <td class="px-3 py-3 text-right tabular-nums">{{ $row['stability'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @else
                            <p class="mt-1 font-body-sm text-on-surface-variant">Điểm ưu tiên cao hơn thì câu dễ được chọn hơn (thuật toán cũ).</p>
                            <div class="mt-3 overflow-x-auto rounded-lg border border-outline-variant">
                                <table class="w-full min-w-[1120px] border-collapse text-left">
                                    <thead>
                                        <tr class="border-b border-outline-variant bg-surface-container-low font-label-sm text-on-surface-variant">
                                            <th class="px-3 py-3 text-right font-medium" scope="col">Hạng</th>
                                            <th class="px-3 py-3 font-medium" scope="col">Mã câu</th>
                                            <th class="bg-primary/5 px-3 py-3 text-right font-semibold text-on-surface" scope="col">Điểm ưu tiên</th>
                                            <th class="bg-primary/5 px-3 py-3 text-right font-semibold text-on-surface" scope="col">% suất vòng đầu</th>
                                            <th class="px-3 py-3 font-medium" scope="col">Vào phiên</th>
                                            <th class="px-3 py-3 text-right font-medium" scope="col">Số lần sai</th>
                                            <th class="px-3 py-3 text-right font-medium" scope="col">Số lần đúng</th>
                                            <th class="px-3 py-3 text-right font-medium" scope="col">Độ yếu</th>
                                            <th class="px-3 py-3 text-right font-medium" scope="col">Ngày từ lần chấm</th>
                                            <th class="px-3 py-3 text-right font-medium" scope="col">Độ bền</th>
                                            <th class="px-3 py-3 text-right font-medium" scope="col">Mức cần ôn</th>
                                            <th class="px-3 py-3 font-medium" scope="col">Tránh lặp</th>
                                            <th class="px-3 py-3 font-medium" scope="col">Cách xét</th>
                                        </tr>
                                    </thead>
                                    <tbody class="font-body-sm text-on-surface">
                                        @foreach ($session['table'] as $row)
                                            <tr @class([
                                                'border-b border-outline-variant/70 last:border-0',
                                                'bg-primary/5' => ($row['chosen'] ?? '') === 'Có',
                                            ])>
                                                <td class="px-3 py-3 text-right tabular-nums text-on-surface-variant">{{ $row['rank'] }}</td>
                                                <td class="px-3 py-3 font-label-md">{{ $row['code'] }}</td>
                                                <td class="bg-primary/5 px-3 py-3 text-right font-label-md tabular-nums">{{ $row['priority'] ?? '—' }}</td>
                                                <td class="bg-primary/5 px-3 py-3 text-right font-label-md tabular-nums">{{ $row['share'] ?? '—' }}</td>
                                                <td class="px-3 py-3">{{ $row['chosen'] ?? '—' }}</td>
                                                <td class="px-3 py-3 text-right tabular-nums">{{ $row['wrong'] ?? '—' }}</td>
                                                <td class="px-3 py-3 text-right tabular-nums">{{ $row['correct'] ?? '—' }}</td>
                                                <td class="px-3 py-3 text-right tabular-nums">{{ $row['weakness'] }}</td>
                                                <td class="px-3 py-3 text-right tabular-nums">{{ $row['days'] }}</td>
                                                <td class="px-3 py-3 text-right tabular-nums">{{ $row['stability'] ?? '—' }}</td>
                                                <td class="px-3 py-3 text-right tabular-nums">{{ $row['memory'] ?? '—' }}</td>
                                                <td class="px-3 py-3">{{ $row['hold'] ?? '—' }}</td>
                                                <td class="px-3 py-3">{{ $row['role'] ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @if (count($session['formulas']) > 0)
                            <h3 class="mt-5 font-label-md text-on-surface">Chi tiết theo pipeline (① → ② → ③)</h3>
                            <dl class="mt-2 space-y-2 rounded-lg bg-surface-container-low px-4 py-3">
                                @foreach ($session['formulas'] as $formula)
                                    <div>
                                        <dt class="font-label-sm font-semibold text-on-surface">{{ $formula['name'] }}</dt>
                                        <dd class="font-body-sm text-on-surface-variant">
                                            {!! preg_replace_callback(
                                                '/\*\*(.+?)\*\*/u',
                                                static fn (array $matches): string => '<strong class="font-bold text-on-surface">'.$matches[1].'</strong>',
                                                e($formula['expr']),
                                            ) !!}
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                    @endif

                    <p class="mt-5 font-body-sm text-on-surface">{{ $session['closing'] }}</p>
                </article>
            @endforeach
        </div>
    @endif
</x-layouts.admin>
