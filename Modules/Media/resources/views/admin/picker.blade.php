@can(\App\Support\Enums\Permission::MediaView->value)
    <div x-data="mediaPicker()" x-cloak x-show="open" class="fixed inset-0 z-[80]"
        @media-picker:open.window="openFromEvent($event)"
        @keydown.escape.window="close()">
        <div class="absolute inset-0 bg-black/40" @click="close()"></div>
        <div class="absolute inset-x-0 bottom-0 mx-auto flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-t-2xl border border-outline-variant bg-surface shadow-xl sm:inset-y-auto sm:bottom-auto sm:top-1/2 sm:-translate-y-1/2 sm:rounded-2xl"
            role="dialog" aria-modal="true" aria-labelledby="media-picker-title">
            <div class="flex items-center justify-between border-b border-outline-variant px-5 py-3">
                <h2 id="media-picker-title" class="font-label-md text-on-surface">Chọn tệp nội dung</h2>
                <button type="button" class="rounded-lg p-1.5 text-on-surface-variant hover:bg-surface-container-low" @click="close()" aria-label="Đóng">
                    <span class="material-symbols-outlined text-[22px]">close</span>
                </button>
            </div>

            <div class="flex gap-1 border-b border-outline-variant px-3">
                <button type="button" class="border-b-2 px-4 py-2.5 font-label-md"
                    :class="tab === 'library' ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant'"
                    @click="tab = 'library'; load(true)">Thư viện</button>
                @can(\App\Support\Enums\Permission::MediaManage->value)
                    <button type="button" class="border-b-2 px-4 py-2.5 font-label-md"
                        :class="tab === 'upload' ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant'"
                        @click="tab = 'upload'">Tải lên</button>
                    <button type="button" class="border-b-2 px-4 py-2.5 font-label-md"
                        :class="tab === 'url' ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant'"
                        @click="tab = 'url'">URL / CDN</button>
                @endcan
            </div>

            <div class="min-h-[22rem] flex-1 overflow-y-auto p-5" x-show="tab === 'library'">
                <input type="search" x-model="q" @input.debounce.300ms="load(true)"
                    placeholder="Tìm theo tên hoặc mô tả thay thế…"
                    class="mb-4 block w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">

                <p x-show="loading && items.length === 0" class="font-body-sm text-on-surface-variant">Đang tải…</p>
                <p x-show="!loading && items.length === 0" class="font-body-sm text-on-surface-variant">Không có tệp phù hợp.</p>

                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    <template x-for="item in items" :key="item.id">
                        <button type="button"
                            class="overflow-hidden rounded-lg border text-left"
                            :class="selected?.id === item.id ? 'border-primary ring-2 ring-primary/30' : 'border-outline-variant'"
                            @click="selected = item"
                            :disabled="!item.ready">
                            <div class="aspect-square bg-surface-container-low">
                                <img x-show="item.thumb_url" :src="item.thumb_url" :alt="item.alt" class="size-full object-cover">
                                <div x-show="!item.thumb_url" class="flex size-full items-center justify-center text-on-surface-variant">
                                    <span class="material-symbols-outlined">movie</span>
                                </div>
                            </div>
                            <p class="truncate px-2 py-1 font-label-sm text-on-surface" x-text="item.original_name || item.alt"></p>
                        </button>
                    </template>
                </div>

                <button type="button" class="mt-4 font-label-sm text-primary" x-show="page < lastPage" @click="loadMore()" :disabled="loading">
                    Tải thêm
                </button>
            </div>

            <div class="min-h-[22rem] flex-1 overflow-y-auto p-5" x-show="tab === 'upload'" x-cloak>
                <div class="rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-4 py-10 text-center"
                    :class="dragging ? 'border-primary bg-primary/5' : ''"
                    @dragover.prevent="dragging = true"
                    @dragleave.prevent="dragging = false"
                    @drop.prevent="dragging = false; uploadFiles($event.dataTransfer.files)">
                    <p class="font-label-md text-on-surface">Kéo thả ảnh / đoạn phim vào đây</p>
                    <p class="mt-1 font-body-sm text-on-surface-variant">Ảnh ≤ 10 MB · Đoạn phim ≤ 100 MB · lưu trên máy chủ</p>
                    <button type="button" class="mt-4 rounded-lg bg-primary px-4 py-2 font-label-md text-on-primary" @click="$refs.file.click()">Chọn tệp</button>
                    <input type="file" class="hidden" x-ref="file" accept="image/*,video/mp4,video/webm,video/quicktime" multiple @change="uploadFiles($event.target.files)">
                </div>
                <p class="mt-3 font-label-sm text-error" x-show="error" x-text="error"></p>
                <ul class="mt-3 space-y-2 font-body-sm text-on-surface-variant">
                    <template x-for="row in uploads" :key="row.name">
                        <li>
                            <span x-text="row.name"></span>
                            — <span x-text="row.status"></span>
                        </li>
                    </template>
                </ul>
            </div>

            <div class="min-h-[22rem] flex-1 overflow-y-auto p-5" x-show="tab === 'url'" x-cloak>
                <label class="mb-1.5 block font-label-sm text-on-surface-variant" for="media-external-url">URL ảnh (https)</label>
                <input id="media-external-url" type="url" x-model="externalUrl" placeholder="https://cdn.example.com/hero.webp"
                    class="block w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">
                <label class="mt-3 mb-1.5 block font-label-sm text-on-surface-variant">Mô tả thay thế (tùy chọn)</label>
                <input type="text" x-model="externalAlt" maxlength="255" placeholder="Mô tả ảnh"
                    class="block w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">
                <label class="mt-3 flex items-start gap-2 font-body-sm text-on-surface">
                    <input type="checkbox" class="mt-0.5" x-model="importLocal">
                    <span>Tải về máy chủ (ổn định hơn; bỏ chọn = dùng đường dẫn gốc, không lưu tệp)</span>
                </label>
                <div class="mt-4 overflow-hidden rounded-xl border border-outline-variant bg-surface-container-low" x-show="externalUrl">
                    <img :src="externalUrl" alt="" class="max-h-48 w-full object-contain" x-on:error="externalPreviewError = true" x-on:load="externalPreviewError = false">
                    <p class="px-3 py-2 font-label-sm text-error" x-show="externalPreviewError">Không xem trước được — vẫn có thể thêm nếu URL hợp lệ.</p>
                </div>
                <p class="mt-3 font-label-sm text-error" x-show="error" x-text="error"></p>
                <button type="button" class="mt-4 rounded-lg bg-primary px-4 py-2 font-label-md text-on-primary disabled:opacity-40"
                    :disabled="!externalUrl || submittingUrl"
                    @click="submitExternalUrl()">
                    <span x-text="submittingUrl ? 'Đang thêm…' : 'Thêm vào thư viện'"></span>
                </button>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-outline-variant px-5 py-3">
                <button type="button" class="rounded-lg px-3 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low" @click="close()">Hủy</button>
                <button type="button"
                    class="rounded-lg bg-primary px-4 py-2 font-label-md text-on-primary disabled:opacity-40"
                    :disabled="!selected || !selected.ready"
                    @click="confirm()">Sử dụng tệp này</button>
            </div>
        </div>
    </div>
@endcan
