@php
    $steps = [
        'upload' => 'Tải tệp',
        'map' => 'Ánh xạ cột',
        'preview' => 'Kiểm tra',
        'done' => 'Xác nhận',
    ];
    $stepIndex = array_search($step, array_keys($steps), true);
@endphp

<x-layouts.admin title="Import câu hỏi — Quản trị nội dung">
    <div class="space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ route('admin.questions.index') }}"
                    class="mb-2 inline-flex items-center gap-1 font-label-sm text-on-surface-variant hover:text-on-surface">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                    Ngân hàng câu hỏi
                </a>
                <h1 class="font-headline-md text-headline-md font-bold tracking-tight text-on-surface">
                    Import câu hỏi hàng loạt
                </h1>
                <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
                    Tải Excel/CSV theo mẫu. Mọi câu được tạo ở trạng thái <strong>nháp</strong> — không xuất bản ngay.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ $templateXlsxUrl }}"
                    class="inline-flex items-center gap-2 rounded-xl border border-outline-variant bg-surface px-4 py-2.5 font-label-md font-semibold text-on-surface hover:bg-surface-container-low">
                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">download</span>
                    Mẫu Excel
                </a>
                <a href="{{ $templateCsvUrl }}"
                    class="inline-flex items-center gap-2 rounded-xl border border-outline-variant bg-surface px-4 py-2.5 font-label-md font-semibold text-on-surface hover:bg-surface-container-low">
                    Mẫu CSV
                </a>
            </div>
        </header>

        <x-admin.flash />

        <ol class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            @foreach ($steps as $key => $label)
                @php $i = array_search($key, array_keys($steps), true); @endphp
                <li
                    class="rounded-xl border px-3 py-2.5 font-label-sm {{ $i <= $stepIndex ? 'border-primary/40 bg-primary/5 text-primary' : 'border-outline-variant text-on-surface-variant' }}">
                    <span class="font-semibold">{{ $i + 1 }}.</span> {{ $label }}
                </li>
            @endforeach
        </ol>

        @if ($step === 'upload')
            <section class="rounded-xl border border-outline-variant bg-surface p-6">
                <h2 class="font-label-lg font-semibold text-on-surface">Tải tệp</h2>
                <p class="mt-1 font-body-sm text-on-surface-variant">
                    Tối đa 500 dòng, 5MB. Cột <code>status</code> / người xuất bản nếu có sẽ bị bỏ qua.
                </p>
                <form method="post" action="{{ route('admin.questions.import.upload') }}" enctype="multipart/form-data"
                    class="mt-5 space-y-4">
                    @csrf
                    <label
                        class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-outline-variant bg-surface-container-low px-6 py-12 text-center hover:border-primary/50">
                        <span class="material-symbols-outlined text-[36px] text-primary" aria-hidden="true">upload_file</span>
                        <span class="mt-2 font-label-md font-semibold text-on-surface">Chọn hoặc kéo thả .xlsx / .csv</span>
                        <input id="import-file" class="sr-only" type="file" name="file" accept=".xlsx,.csv,text/csv"
                            required>
                    </label>
                    @error('file')
                        <p class="font-body-sm text-error">{{ $message }}</p>
                    @enderror
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 font-label-md font-semibold text-on-primary hover:bg-primary/90">
                        Tiếp tục ánh xạ cột
                    </button>
                </form>
            </section>
        @endif

        @if ($step === 'map' && $batch)
            <section class="rounded-xl border border-outline-variant bg-surface p-6">
                <h2 class="font-label-lg font-semibold text-on-surface">Ánh xạ cột</h2>
                <p class="mt-1 font-body-sm text-on-surface-variant">
                    Tệp: <strong>{{ $batch->original_filename }}</strong>. Ghép cột tệp với trường hệ thống.
                </p>
                @error('column_map')
                    <p class="mt-3 font-body-sm text-error">{{ $message }}</p>
                @enderror
                <form method="post" action="{{ route('admin.questions.import.map', $batch) }}" class="mt-5 space-y-3">
                    @csrf
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left font-body-sm">
                            <thead>
                                <tr class="border-b border-outline-variant text-on-surface-variant">
                                    <th class="px-3 py-2">Trường hệ thống</th>
                                    <th class="px-3 py-2">Cột trong tệp</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($fields as $field => $meta)
                                    <tr class="border-b border-outline-variant/60">
                                        <td class="px-3 py-2">
                                            <span class="font-semibold text-on-surface">{{ $meta['label'] }}</span>
                                            @if ($meta['required'])
                                                <span class="text-error">*</span>
                                            @endif
                                            <span class="block text-xs text-on-surface-variant">{{ $field }}</span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <select name="column_map[{{ $field }}]"
                                                class="h-10 w-full max-w-md rounded-lg border border-outline-variant bg-surface-container-low px-3">
                                                <option value="">— Bỏ qua —</option>
                                                @foreach ($batch->source_headers ?? [] as $index => $header)
                                                    <option value="{{ $index }}" @selected((string) ($batch->column_map[$field] ?? '') === (string) $index)>
                                                        {{ $header !== '' ? $header : 'Cột '.($index + 1) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="flex flex-wrap gap-2 pt-2">
                        <a href="{{ route('admin.questions.import') }}"
                            class="inline-flex items-center rounded-xl border border-outline-variant px-4 py-2.5 font-label-md font-semibold text-on-surface hover:bg-surface-container-low">
                            Tải tệp khác
                        </a>
                        <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 font-label-md font-semibold text-on-primary hover:bg-primary/90">
                            Kiểm tra dữ liệu
                        </button>
                    </div>
                </form>
            </section>
        @endif

        @if ($step === 'preview' && $batch && $preview)
            <section class="space-y-4">
                <div class="rounded-xl border border-outline-variant bg-surface p-6">
                    <h2 class="font-label-lg font-semibold text-on-surface">Kiểm tra</h2>
                    <p class="mt-2 font-body-sm text-on-surface">
                        <strong>{{ number_format($preview['valid']) }}</strong> hợp lệ ·
                        <strong>{{ number_format($preview['invalid']) }}</strong> lỗi
                        trên {{ number_format($preview['total']) }} dòng.
                    </p>
                    <p class="mt-1 font-body-sm text-on-surface-variant">
                        Chỉ các dòng hợp lệ được tạo thành bản nháp. Không gửi duyệt và không xuất bản.
                    </p>
                    @if ($batch->error_report_path)
                        <a href="{{ route('admin.questions.import.errors', $batch) }}"
                            class="mt-3 inline-flex font-label-sm font-semibold text-primary hover:underline">
                            Tải danh sách dòng lỗi
                        </a>
                    @endif
                </div>

                <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left font-body-sm">
                            <thead>
                                <tr class="border-b border-outline-variant bg-surface-container-low text-on-surface-variant">
                                    <th class="px-3 py-2">Dòng</th>
                                    <th class="px-3 py-2">Đề bài</th>
                                    <th class="px-3 py-2">Kết quả</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (array_slice($preview['rows'], 0, 80) as $row)
                                    <tr class="border-b border-outline-variant/60">
                                        <td class="px-3 py-2 tabular-nums">{{ $row['line'] }}</td>
                                        <td class="max-w-xl px-3 py-2 text-on-surface">
                                            {{ \Illuminate\Support\Str::limit($row['values']['stem'] ?? '', 140) }}
                                        </td>
                                        <td class="px-3 py-2">
                                            @if ($row['ok'])
                                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Hợp lệ</span>
                                            @else
                                                <span class="text-error">{{ implode(' ', $row['errors']) }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.questions.import.show', $batch) }}?remap=1"
                        class="inline-flex items-center rounded-xl border border-outline-variant px-4 py-2.5 font-label-md font-semibold text-on-surface hover:bg-surface-container-low">
                        Sửa ánh xạ
                    </a>
                    <form method="post" action="{{ route('admin.questions.import.commit', $batch) }}">
                        @csrf
                        <button type="submit" @if ($preview['valid'] === 0) disabled @endif
                            class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 font-label-md font-semibold text-on-primary hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50">
                            Import {{ number_format($preview['valid']) }} câu (tạo bản nháp)
                        </button>
                    </form>
                </div>
            </section>
        @endif

        @if ($step === 'done' && $batch)
            <section class="rounded-xl border border-outline-variant bg-surface p-6">
                <h2 class="font-label-lg font-semibold text-on-surface">Đã ghi bản nháp</h2>
                <p class="mt-2 font-body-sm text-on-surface-variant">
                    Tạo {{ number_format((int) ($batch->stats['created'] ?? 0)) }} câu hỏi
                    · bỏ qua {{ number_format((int) ($batch->stats['skipped'] ?? $batch->stats['invalid'] ?? 0)) }} dòng lỗi.
                    Học viên chưa thấy các câu này.
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('admin.questions.index', ['import_batch_id' => $batch->getKey(), 'status' => 'draft']) }}"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 font-label-md font-semibold text-on-primary hover:bg-primary/90">
                        Xem câu vừa import
                    </a>
                    <a href="{{ route('admin.questions.import') }}"
                        class="inline-flex items-center rounded-xl border border-outline-variant px-4 py-2.5 font-label-md font-semibold text-on-surface hover:bg-surface-container-low">
                        Import lô khác
                    </a>
                </div>
            </section>
        @endif
    </div>
</x-layouts.admin>
