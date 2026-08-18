@php
    use Modules\Admin\Support\Cms\CmsPageSeo;
    use Modules\Admin\Support\Enums\CmsPageKey;

    $seo = $page->resolvedSeo();
    $isPublished = $page->isPublished();
    $isLanding = $page->key?->isLandingBlock() ?? false;
    $publicPath = $page->key?->slug() ?? '';
    $publicUrl = $page->key ? route($page->key->routeName()) : null;
    $listUrl = $isLanding
        ? route('admin.cms.pages.index', ['group' => 'landing'])
        : route('admin.cms.pages.index');
    $formView = match ($page->key) {
        CmsPageKey::Home => 'admin::cms.pages.forms.home',
        CmsPageKey::Features => 'admin::cms.pages.forms.features',
        CmsPageKey::About => 'admin::cms.pages.forms.about',
        CmsPageKey::Contact => 'admin::cms.pages.forms.contact',
        CmsPageKey::Terms, CmsPageKey::Privacy => 'admin::cms.pages.forms.legal',
        default => null,
    };
@endphp

<x-layouts.admin :title="$isLanding ? 'CMS — Sửa Landing' : 'CMS — Sửa trang tĩnh'">
    @include('admin::cms._sub-nav')

    <x-admin.page-header :title="'Sửa: '.$page->key?->label()"
        :description="$isLanding
            ? 'Chỉnh copy/ảnh theo section. Trang luôn mở công khai — nháp dùng nội dung mặc định.'
            : 'Chỉnh nội dung và SEO. Chỉ trang đã xuất bản mới mở được URL công khai.'">
        <x-slot:actions>
            <a href="{{ $listUrl }}"
                class="inline-flex items-center rounded-lg px-3 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">← Danh sách</a>
            @if ($publicUrl && ($isPublished || $isLanding))
                <a href="{{ $publicUrl }}" target="_blank" rel="noopener noreferrer"
                    class="inline-flex items-center gap-1 rounded-lg border border-outline-variant px-3 py-2 font-label-md text-on-surface hover:bg-surface-container-low">
                    Xem trên web
                    <span class="material-symbols-outlined text-[18px] leading-none">open_in_new</span>
                </a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <form method="post" action="{{ route('admin.cms.pages.update', $page) }}" class="w-full max-w-4xl space-y-6">
        @csrf
        @method('PUT')

        {{-- Trạng thái xuất bản --}}
        <div @class([
            'rounded-xl border p-5',
            'border-primary/30 bg-primary/5' => $isPublished,
            'border-outline-variant bg-surface' => ! $isPublished,
        ])>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-label-md text-label-md text-on-surface">Trạng thái xuất bản</h3>
                        @if ($isPublished)
                            <span class="rounded-full bg-primary/15 px-2.5 py-0.5 font-label-sm text-primary">Đang xuất bản</span>
                        @else
                            <span class="rounded-full bg-surface-container-high px-2.5 py-0.5 font-label-sm text-on-surface-variant">Ngừng xuất bản</span>
                        @endif
                    </div>

                    @if ($isPublished)
                        <p class="font-body-sm text-on-surface-variant">
                            Xuất bản lần đầu: {{ $page->published_at?->format('d/m/Y H:i') ?? '—' }}
                        </p>
                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                            <span class="shrink-0 font-label-sm text-on-surface-variant">URL công khai</span>
                            <code class="max-w-full truncate rounded-md bg-surface px-2 py-1 font-mono text-xs text-on-surface">{{ $publicUrl }}</code>
                            <a href="{{ $publicUrl }}" target="_blank" rel="noopener noreferrer"
                                class="font-label-sm text-primary hover:underline">Mở ↗</a>
                        </div>
                    @elseif ($isLanding)
                        <p class="font-body-sm text-on-surface-variant">
                            URL <code class="rounded bg-surface-container-low px-1.5 py-0.5 text-xs">{{ $publicPath }}</code>
                            vẫn mở công khai với <strong class="font-semibold text-on-surface">nội dung mặc định</strong>
                            (chưa dùng bản CMS đang lưu nháp).
                        </p>
                    @else
                        <p class="font-body-sm text-on-surface-variant">
                            URL <code class="rounded bg-surface-container-low px-1.5 py-0.5 text-xs">{{ $publicPath }}</code>
                            hiện trả về <strong class="font-semibold text-on-surface">404</strong> cho người dùng.
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Thông tin cơ bản --}}
        <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
            <div class="border-b border-outline-variant px-5 py-4">
                <h3 class="font-label-md text-label-md text-on-surface">Thông tin trang</h3>
            </div>
            <div class="space-y-4 p-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="min-w-0">
                        <span class="mb-1.5 block font-label-sm text-label-sm text-on-surface-variant">Key hệ thống</span>
                        <p class="font-label-md text-on-surface">{{ $page->key?->value }}</p>
                    </div>
                    <div class="min-w-0">
                        <span class="mb-1.5 block font-label-sm text-label-sm text-on-surface-variant">Đường dẫn</span>
                        <p class="font-mono text-sm text-on-surface">{{ $publicPath }}</p>
                    </div>
                </div>

                <div class="min-w-0">
                    <label class="mb-1.5 block font-label-sm text-label-sm text-on-surface-variant" for="title">Tiêu đề trang <span class="text-error">*</span></label>
                    <input id="title" name="title" type="text" required maxlength="255"
                        value="{{ old('title', $page->title) }}"
                        class="block w-full min-w-0 rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
                    @error('title')
                        <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        @if ($formView)
            <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
                <div class="border-b border-outline-variant px-5 py-4">
                    <h3 class="font-label-md text-label-md text-on-surface">Nội dung theo từng phần</h3>
                    <p class="mt-1 font-label-sm text-on-surface-variant">Chỉ nhập văn bản / chọn ảnh từ thư viện — bố cục HTML cố định trên website.</p>
                </div>
                <div class="p-5">
                    @include($formView, ['content' => $content])
                </div>
            </div>
        @endif

        {{-- SEO snippet --}}
        <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
            <div class="border-b border-outline-variant px-5 py-4">
                <h3 class="font-label-md text-label-md text-on-surface">SEO — Snippet tìm kiếm</h3>
                <p class="mt-1 font-label-sm text-on-surface-variant">Tối ưu tiêu đề &amp; mô tả hiển thị trên Google (kiểu Yoast).</p>
            </div>
            <div class="space-y-4 p-5">
                <div class="min-w-0">
                    <label class="mb-1.5 block font-label-sm text-label-sm text-on-surface-variant" for="focus_keyword">Focus keyphrase</label>
                    <input id="focus_keyword" name="focus_keyword" type="text" maxlength="100"
                        value="{{ old('focus_keyword', $seo['focus_keyword'] ?? '') }}"
                        placeholder="Từ khóa chính muốn xếp hạng"
                        class="block w-full min-w-0 rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
                    @error('focus_keyword')
                        <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="min-w-0" x-data="{ len: {{ mb_strlen((string) old('meta_title', $seo['meta_title'] ?? '')) }} }">
                    <label class="mb-1.5 block font-label-sm text-label-sm text-on-surface-variant" for="meta_title">SEO title (≤70 ký tự)</label>
                    <input id="meta_title" name="meta_title" type="text" maxlength="70"
                        value="{{ old('meta_title', $seo['meta_title'] ?? '') }}"
                        placeholder="Tiêu đề trên Google"
                        class="block w-full min-w-0 rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary"
                        x-on:input="len = $event.target.value.length">
                    <p class="mt-1 font-label-sm text-on-surface-variant"><span x-text="len">0</span>/70</p>
                    @error('meta_title')
                        <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="min-w-0" x-data="{ len: {{ mb_strlen((string) old('meta_description', $seo['meta_description'] ?? '')) }} }">
                    <label class="mb-1.5 block font-label-sm text-label-sm text-on-surface-variant" for="meta_description">Meta description (≤160 ký tự)</label>
                    <textarea id="meta_description" name="meta_description" rows="3" maxlength="160"
                        placeholder="Mô tả ngắn cho kết quả tìm kiếm"
                        class="block w-full min-w-0 resize-y rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary"
                        x-on:input="len = $event.target.value.length">{{ old('meta_description', $seo['meta_description'] ?? '') }}</textarea>
                    <p class="mt-1 font-label-sm text-on-surface-variant"><span x-text="len">0</span>/160</p>
                    @error('meta_description')
                        <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="min-w-0">
                    <label class="mb-1.5 block font-label-sm text-label-sm text-on-surface-variant" for="meta_keywords">Meta keywords</label>
                    <input id="meta_keywords" name="meta_keywords" type="text" maxlength="255"
                        value="{{ old('meta_keywords', $seo['meta_keywords'] ?? '') }}"
                        placeholder="Từ khóa phụ, cách nhau bằng dấu phẩy"
                        class="block w-full min-w-0 rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
                    @error('meta_keywords')
                        <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="min-w-0">
                    <label class="mb-1.5 block font-label-sm text-label-sm text-on-surface-variant" for="canonical_url">Canonical URL</label>
                    <input id="canonical_url" name="canonical_url" type="text" maxlength="2048"
                        value="{{ old('canonical_url', $seo['canonical_url'] ?? '') }}"
                        placeholder="Để trống = URL trang hiện tại"
                        class="block w-full min-w-0 rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
                    @error('canonical_url')
                        <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="min-w-0">
                        <label class="mb-1.5 block font-label-sm text-label-sm text-on-surface-variant" for="robots_index">Robots index</label>
                        <select id="robots_index" name="robots_index"
                            class="block w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
                            <option value="index" @selected(old('robots_index', $seo['robots_index'] ?? 'index') === 'index')>index</option>
                            <option value="noindex" @selected(old('robots_index', $seo['robots_index'] ?? 'index') === 'noindex')>noindex</option>
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label class="mb-1.5 block font-label-sm text-label-sm text-on-surface-variant" for="robots_follow">Robots follow</label>
                        <select id="robots_follow" name="robots_follow"
                            class="block w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
                            <option value="follow" @selected(old('robots_follow', $seo['robots_follow'] ?? 'follow') === 'follow')>follow</option>
                            <option value="nofollow" @selected(old('robots_follow', $seo['robots_follow'] ?? 'follow') === 'nofollow')>nofollow</option>
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label class="mb-1.5 block font-label-sm text-label-sm text-on-surface-variant" for="schema_type">Schema type</label>
                        <select id="schema_type" name="schema_type"
                            class="block w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
                            @foreach (CmsPageSeo::schemaTypes() as $type)
                                <option value="{{ $type }}" @selected(old('schema_type', $seo['schema_type'] ?? '') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- Open Graph --}}
        <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
            <div class="border-b border-outline-variant px-5 py-4">
                <h3 class="font-label-md text-label-md text-on-surface">SEO — Open Graph</h3>
                <p class="mt-1 font-label-sm text-on-surface-variant">Facebook / Zalo… — để trống sẽ dùng SEO title / description.</p>
            </div>
            <div class="space-y-4 p-5">
                @include('admin::cms.pages.forms._field', ['label' => 'OG title', 'name' => 'og_title', 'value' => old('og_title', $seo['og_title'] ?? ''), 'required' => false])
                @include('admin::cms.pages.forms._field', ['label' => 'OG description', 'name' => 'og_description', 'type' => 'textarea', 'rows' => 2, 'value' => old('og_description', $seo['og_description'] ?? ''), 'required' => false])
                <x-admin.image-slot
                    label="OG image"
                    media-id-name="og_image_media_id"
                    url-name="og_image"
                    :media-id="old('og_image_media_id', $seo['og_image_media_id'] ?? null)"
                    :url="old('og_image', $seo['og_image'] ?? '')"
                    aspect="1.91/1"
                    :required="false"
                    hint="Khuyến nghị 1200×630. Tải lên hoặc chọn từ thư viện."
                />
            </div>
        </div>

        {{-- Twitter --}}
        <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
            <div class="border-b border-outline-variant px-5 py-4">
                <h3 class="font-label-md text-label-md text-on-surface">SEO — Twitter Card</h3>
            </div>
            <div class="space-y-4 p-5">
                @include('admin::cms.pages.forms._field', ['label' => 'Twitter title', 'name' => 'twitter_title', 'value' => old('twitter_title', $seo['twitter_title'] ?? ''), 'required' => false])
                @include('admin::cms.pages.forms._field', ['label' => 'Twitter description', 'name' => 'twitter_description', 'type' => 'textarea', 'rows' => 2, 'value' => old('twitter_description', $seo['twitter_description'] ?? ''), 'required' => false])
                <x-admin.image-slot
                    label="Twitter image"
                    media-id-name="twitter_image_media_id"
                    url-name="twitter_image"
                    :media-id="old('twitter_image_media_id', $seo['twitter_image_media_id'] ?? null)"
                    :url="old('twitter_image', $seo['twitter_image'] ?? '')"
                    aspect="1.91/1"
                    :required="false"
                    hint="Để trống sẽ dùng OG image."
                />
            </div>
        </div>

        {{-- Actions --}}
        <div class="sticky bottom-0 z-10 -mx-1 border-t border-outline-variant bg-surface-container-lowest/95 px-1 py-4 backdrop-blur supports-[backdrop-filter]:bg-surface-container-lowest/80">
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                @if ($isPublished)
                    <button type="submit" name="action" value="save"
                        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 font-label-md text-on-primary hover:opacity-90">
                        Lưu thay đổi
                    </button>
                    <button type="submit" name="action" value="unpublish"
                        class="inline-flex items-center justify-center rounded-lg border border-outline-variant px-4 py-2.5 font-label-md text-on-surface hover:bg-surface-container-low"
                        onclick="return confirm(@js($isLanding
                            ? 'Ngừng xuất bản? Trang public sẽ quay về nội dung mặc định.'
                            : 'Ngừng xuất bản trang này? URL công khai sẽ trả về 404.'))">
                        Ngừng xuất bản
                    </button>
                @else
                    <button type="submit" name="action" value="publish"
                        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 font-label-md text-on-primary hover:opacity-90">
                        Xuất bản
                    </button>
                    <button type="submit" name="action" value="save"
                        class="inline-flex items-center justify-center rounded-lg border border-outline-variant px-4 py-2.5 font-label-md text-on-surface hover:bg-surface-container-low">
                        Lưu nháp
                    </button>
                @endif
            </div>
        </div>
    </form>
</x-layouts.admin>
