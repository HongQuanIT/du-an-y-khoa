@php
    $c = $content;
@endphp

<div class="divide-y divide-outline-variant/70">
    <section class="space-y-4 pb-6">
        <h4 class="font-label-md text-label-md text-on-surface">Hero</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề', 'name' => 'content[hero][title]', 'value' => $c['hero']['title'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Mô tả ngắn', 'name' => 'content[hero][subtitle]', 'type' => 'textarea', 'rows' => 2, 'value' => $c['hero']['subtitle'], 'required' => true])
    </section>

    <section class="space-y-4 py-6">
        <h4 class="font-label-md text-label-md text-on-surface">Câu chuyện ra đời</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề section', 'name' => 'content[story][heading]', 'value' => $c['story']['heading'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Đoạn 1', 'name' => 'content[story][paragraph_1]', 'type' => 'textarea', 'rows' => 4, 'value' => $c['story']['paragraph_1'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Đoạn 2', 'name' => 'content[story][paragraph_2]', 'type' => 'textarea', 'rows' => 4, 'value' => $c['story']['paragraph_2'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Tagline', 'name' => 'content[story][tagline]', 'value' => $c['story']['tagline'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'URL ảnh', 'name' => 'content[story][image_url]', 'type' => 'url', 'value' => $c['story']['image_url'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Mô tả ảnh (alt)', 'name' => 'content[story][image_alt]', 'value' => $c['story']['image_alt'], 'required' => true])
    </section>

    <section class="space-y-4 py-6">
        <h4 class="font-label-md text-label-md text-on-surface">Giá trị cốt lõi</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề section', 'name' => 'content[values][heading]', 'value' => $c['values']['heading'], 'required' => true])
        @foreach ($c['values']['items'] as $index => $item)
            <div class="space-y-3 rounded-lg bg-surface-container-lowest p-4">
                <p class="font-label-sm text-on-surface-variant">Giá trị #{{ $index + 1 }}</p>
                @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề', 'name' => "content[values][items][{$index}][title]", 'value' => $item['title'], 'required' => true])
                @include('admin::cms.pages.forms._field', ['label' => 'Mô tả', 'name' => "content[values][items][{$index}][description]", 'type' => 'textarea', 'rows' => 2, 'value' => $item['description'], 'required' => true])
            </div>
        @endforeach
    </section>

    <section class="space-y-4 py-6">
        <h4 class="font-label-md text-label-md text-on-surface">Thành tựu</h4>
        @foreach ($c['stats']['items'] as $index => $stat)
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @include('admin::cms.pages.forms._field', ['label' => 'Số liệu #'.($index + 1), 'name' => "content[stats][items][{$index}][value]", 'value' => $stat['value'], 'required' => true])
                @include('admin::cms.pages.forms._field', ['label' => 'Nhãn #'.($index + 1), 'name' => "content[stats][items][{$index}][label]", 'value' => $stat['label'], 'required' => true])
            </div>
        @endforeach
    </section>

    <section class="space-y-4 py-6">
        <h4 class="font-label-md text-label-md text-on-surface">Đội ngũ chuyên gia</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề section', 'name' => 'content[experts][heading]', 'value' => $c['experts']['heading'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Mô tả phụ', 'name' => 'content[experts][subtitle]', 'type' => 'textarea', 'rows' => 2, 'value' => $c['experts']['subtitle'], 'required' => true])
        @foreach ($c['experts']['items'] as $index => $expert)
            <div class="space-y-3 rounded-lg bg-surface-container-lowest p-4">
                <p class="font-label-sm text-on-surface-variant">Chuyên gia #{{ $index + 1 }}</p>
                @include('admin::cms.pages.forms._field', ['label' => 'Họ tên', 'name' => "content[experts][items][{$index}][name]", 'value' => $expert['name'], 'required' => true])
                @include('admin::cms.pages.forms._field', ['label' => 'Vai trò', 'name' => "content[experts][items][{$index}][role]", 'value' => $expert['role'], 'required' => true])
                @include('admin::cms.pages.forms._field', ['label' => 'URL ảnh', 'name' => "content[experts][items][{$index}][image_url]", 'type' => 'url', 'value' => $expert['image_url'], 'required' => true])
            </div>
        @endforeach
    </section>

    <section class="space-y-4 py-6">
        <h4 class="font-label-md text-label-md text-on-surface">Đối tác</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Nhãn section', 'name' => 'content[partners][label]', 'value' => $c['partners']['label'], 'required' => true])
        @foreach ($c['partners']['items'] as $index => $partner)
            @include('admin::cms.pages.forms._field', ['label' => 'Đối tác #'.($index + 1), 'name' => "content[partners][items][{$index}]", 'value' => $partner, 'required' => true])
        @endforeach
    </section>

    <section class="space-y-4 pt-6">
        <h4 class="font-label-md text-label-md text-on-surface">Kêu gọi hành động (CTA)</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề', 'name' => 'content[cta][title]', 'value' => $c['cta']['title'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Mô tả', 'name' => 'content[cta][subtitle]', 'type' => 'textarea', 'rows' => 2, 'value' => $c['cta']['subtitle'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Nút chính', 'name' => 'content[cta][primary_label]', 'value' => $c['cta']['primary_label'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Nút phụ', 'name' => 'content[cta][secondary_label]', 'value' => $c['cta']['secondary_label'], 'required' => true])
    </section>
</div>
