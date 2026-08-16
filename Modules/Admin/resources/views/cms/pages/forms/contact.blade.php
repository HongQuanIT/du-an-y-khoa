@php
    $c = $content;
@endphp

<div class="divide-y divide-outline-variant/70">
    <section class="space-y-4 pb-6">
        <h4 class="font-label-md text-label-md text-on-surface">Giới thiệu</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề', 'name' => 'content[intro][title]', 'value' => $c['intro']['title'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Mô tả', 'name' => 'content[intro][text]', 'type' => 'textarea', 'rows' => 3, 'value' => $c['intro']['text'], 'required' => true])
    </section>

    <section class="space-y-4 py-6">
        <h4 class="font-label-md text-label-md text-on-surface">Thông tin liên hệ</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Email', 'name' => 'content[email]', 'type' => 'email', 'value' => $c['email'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Hotline', 'name' => 'content[hotline]', 'value' => $c['hotline'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Địa chỉ', 'name' => 'content[address]', 'type' => 'textarea', 'rows' => 2, 'value' => $c['address'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Giờ làm việc', 'name' => 'content[hours]', 'value' => $c['hours'], 'required' => true])
    </section>

    <section class="space-y-4 pt-6">
        <h4 class="font-label-md text-label-md text-on-surface">Bản đồ</h4>
        @include('admin::cms.pages.forms._image', ['label' => 'Ảnh bản đồ', 'prefix' => 'content[map]', 'value' => $c['map'], 'aspect' => '16/9'])
    </section>
</div>
