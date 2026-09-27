<x-layouts.admin title="Cách chọn câu thích ứng">
    <x-admin.page-header title="Cách hệ thống chọn câu cho học viên"
        description="Mỗi lần tạo phiên là một bảng riêng. Câu có điểm ưu tiên cao hơn đứng trên. Điểm này quyết định câu nào dễ được chọn vào phiên.">
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-outline-variant bg-surface px-3 py-1.5 font-label-sm text-label-sm text-on-surface-variant">
                <span class="material-symbols-outlined text-[16px]">schedule</span>
                Tải lại trang sau khi tạo phiên mới
            </span>
        </x-slot:actions>
    </x-admin.page-header>

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
                Tạo phiên thích ứng xong, tải lại trang này.
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
                    </div>

                    <h2 class="mt-3 font-title-md text-on-surface">{{ $session['headline'] }}</h2>
                    <p class="mt-2 max-w-3xl font-body-sm text-on-surface-variant">{{ $session['summary'] }}</p>

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
                        <p class="mt-1 font-body-sm text-on-surface-variant">Điểm ưu tiên cao hơn thì câu dễ được chọn hơn. Câu mới chưa làm được lấy đều, nên không có điểm ưu tiên.</p>
                        <div class="mt-3 overflow-x-auto rounded-lg border border-outline-variant">
                            <table class="w-full min-w-[980px] border-collapse text-left">
                                <thead>
                                    <tr class="border-b border-outline-variant bg-surface-container-low font-label-sm text-on-surface-variant">
                                        <th class="px-3 py-3 text-right font-medium" scope="col">Hạng</th>
                                        <th class="px-3 py-3 font-medium" scope="col">Mã câu</th>
                                        <th class="bg-primary/5 px-3 py-3 text-right font-semibold text-on-surface" scope="col">Điểm ưu tiên</th>
                                        <th class="bg-primary/5 px-3 py-3 text-right font-semibold text-on-surface" scope="col">% suất vào nhóm ôn</th>
                                        <th class="px-3 py-3 font-medium" scope="col">Vào phiên</th>
                                        <th class="px-3 py-3 text-right font-medium" scope="col">Số lần sai</th>
                                        <th class="px-3 py-3 text-right font-medium" scope="col">Số lần đúng</th>
                                        <th class="px-3 py-3 text-right font-medium" scope="col">Độ yếu</th>
                                        <th class="px-3 py-3 text-right font-medium" scope="col">Ngày chưa gặp</th>
                                        <th class="px-3 py-3 text-right font-medium" scope="col">Mức nhớ</th>
                                        <th class="px-3 py-3 font-medium" scope="col">Tránh lặp</th>
                                        <th class="px-3 py-3 font-medium" scope="col">Cách xét</th>
                                    </tr>
                                </thead>
                                <tbody class="font-body-sm text-on-surface">
                                    @foreach ($session['table'] as $row)
                                        <tr @class([
                                            'border-b border-outline-variant/70 last:border-0',
                                            'bg-primary/5' => $row['chosen'] === 'Có',
                                        ])>
                                            <td class="px-3 py-3 text-right tabular-nums text-on-surface-variant">{{ $row['rank'] }}</td>
                                            <td class="px-3 py-3 font-label-md">{{ $row['code'] }}</td>
                                            <td class="bg-primary/5 px-3 py-3 text-right font-label-md tabular-nums">{{ $row['priority'] }}</td>
                                            <td class="bg-primary/5 px-3 py-3 text-right font-label-md tabular-nums">{{ $row['share'] }}</td>
                                            <td class="px-3 py-3">{{ $row['chosen'] }}</td>
                                            <td class="px-3 py-3 text-right tabular-nums">{{ $row['wrong'] }}</td>
                                            <td class="px-3 py-3 text-right tabular-nums">{{ $row['correct'] }}</td>
                                            <td class="px-3 py-3 text-right tabular-nums">{{ $row['weakness'] }}</td>
                                            <td class="px-3 py-3 text-right tabular-nums">{{ $row['days'] }}</td>
                                            <td class="px-3 py-3 text-right tabular-nums">{{ $row['memory'] }}</td>
                                            <td class="px-3 py-3">{{ $row['hold'] }}</td>
                                            <td class="px-3 py-3">{{ $row['role'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if (count($session['formulas']) > 0)
                            <h3 class="mt-5 font-label-md text-on-surface">Công thức các chỉ số</h3>
                            <dl class="mt-2 space-y-2 rounded-lg bg-surface-container-low px-4 py-3">
                                @foreach ($session['formulas'] as $formula)
                                    <div>
                                        <dt class="font-label-sm text-on-surface">{{ $formula['name'] }}</dt>
                                        <dd class="font-body-sm text-on-surface-variant">{{ $formula['expr'] }}</dd>
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
