@php
    $c = $content;
@endphp

<div class="divide-y divide-outline-variant/70">
    <section class="space-y-4 pb-6">
        <h4 class="font-label-md text-label-md text-on-surface">Phần mở đầu</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Đoạn giới thiệu', 'name' => 'content[intro]', 'type' => 'textarea', 'rows' => 3, 'value' => $c['intro'], 'required' => true])
    </section>

    <section class="space-y-4 pt-6">
        <h4 class="font-label-md text-label-md text-on-surface">Các mục nội dung</h4>
        <p class="font-label-sm text-on-surface-variant">Mỗi mục gồm tiêu đề và nội dung. Xuống dòng trong nội dung sẽ tạo thành các đoạn văn riêng.</p>
        @foreach ($c['sections'] as $index => $section)
            <div class="space-y-3 rounded-lg bg-surface-container-lowest p-4">
                <p class="font-label-sm text-on-surface-variant">Mục #{{ $index + 1 }}</p>
                @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề', 'name' => "content[sections][{$index}][title]", 'value' => $section['title'], 'required' => true])
                @include('admin::cms.pages.forms._field', ['label' => 'Nội dung', 'name' => "content[sections][{$index}][body]", 'type' => 'textarea', 'rows' => 4, 'value' => $section['body'], 'required' => true])
            </div>
        @endforeach
    </section>
</div>
