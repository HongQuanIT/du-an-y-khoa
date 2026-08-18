@php
    $c = $content;
@endphp

<div class="divide-y divide-outline-variant/70">
    <section class="space-y-4 pb-6">
        <h4 class="font-label-md text-label-md text-on-surface">Hero</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề', 'name' => 'content[hero][title]', 'value' => $c['hero']['title'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Mô tả ngắn', 'name' => 'content[hero][subtitle]', 'type' => 'textarea', 'rows' => 2, 'value' => $c['hero']['subtitle'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Nút chính', 'name' => 'content[hero][primary_cta_label]', 'value' => $c['hero']['primary_cta_label'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Nút phụ', 'name' => 'content[hero][secondary_cta_label]', 'value' => $c['hero']['secondary_cta_label'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'URL video', 'name' => 'content[hero][video_url]', 'value' => $c['hero']['video_url'], 'required' => true])
    </section>

    <section class="space-y-4 py-6">
        <h4 class="font-label-md text-label-md text-on-surface">Bento — QBank</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề', 'name' => 'content[bento][qbank][title]', 'value' => $c['bento']['qbank']['title'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Nội dung', 'name' => 'content[bento][qbank][body]', 'type' => 'textarea', 'rows' => 3, 'value' => $c['bento']['qbank']['body'], 'required' => true])
        @foreach ($c['bento']['qbank']['tags'] as $index => $tag)
            @include('admin::cms.pages.forms._field', ['label' => 'Tag #'.($index + 1), 'name' => "content[bento][qbank][tags][{$index}]", 'value' => $tag, 'required' => true])
        @endforeach
        @include('admin::cms.pages.forms._image', ['label' => 'Ảnh QBank', 'prefix' => 'content[bento][qbank]', 'value' => $c['bento']['qbank'], 'aspect' => '4/3'])
    </section>

    <section class="space-y-4 py-6">
        <h4 class="font-label-md text-label-md text-on-surface">Bento — Study/Exam</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề', 'name' => 'content[bento][study_exam][title]', 'value' => $c['bento']['study_exam']['title'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Nội dung', 'name' => 'content[bento][study_exam][body]', 'type' => 'textarea', 'rows' => 3, 'value' => $c['bento']['study_exam']['body'], 'required' => true])
    </section>

    <section class="space-y-4 py-6">
        <h4 class="font-label-md text-label-md text-on-surface">Bento — Flashcards</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề', 'name' => 'content[bento][flashcards][title]', 'value' => $c['bento']['flashcards']['title'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Nội dung', 'name' => 'content[bento][flashcards][body]', 'type' => 'textarea', 'rows' => 3, 'value' => $c['bento']['flashcards']['body'], 'required' => true])
    </section>

    <section class="space-y-4 py-6">
        <h4 class="font-label-md text-label-md text-on-surface">Bento — AI Tutor</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề', 'name' => 'content[bento][ai_tutor][title]', 'value' => $c['bento']['ai_tutor']['title'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Nội dung', 'name' => 'content[bento][ai_tutor][body]', 'type' => 'textarea', 'rows' => 3, 'value' => $c['bento']['ai_tutor']['body'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Nút CTA', 'name' => 'content[bento][ai_tutor][cta_label]', 'value' => $c['bento']['ai_tutor']['cta_label'], 'required' => true])
        @include('admin::cms.pages.forms._image', ['label' => 'Ảnh AI Tutor', 'prefix' => 'content[bento][ai_tutor]', 'value' => $c['bento']['ai_tutor'], 'aspect' => '4/3'])
    </section>

    <section class="space-y-4 py-6">
        <h4 class="font-label-md text-label-md text-on-surface">Bento — Analytics</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề', 'name' => 'content[bento][analytics][title]', 'value' => $c['bento']['analytics']['title'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Badge', 'name' => 'content[bento][analytics][badge]', 'value' => $c['bento']['analytics']['badge'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Nội dung', 'name' => 'content[bento][analytics][body]', 'type' => 'textarea', 'rows' => 3, 'value' => $c['bento']['analytics']['body'], 'required' => true])
        @include('admin::cms.pages.forms._image', ['label' => 'Ảnh Analytics', 'prefix' => 'content[bento][analytics]', 'value' => $c['bento']['analytics'], 'aspect' => '4/3'])
    </section>

    <section class="space-y-4 py-6">
        <h4 class="font-label-md text-label-md text-on-surface">Bento — Library</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề', 'name' => 'content[bento][library][title]', 'value' => $c['bento']['library']['title'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Nội dung', 'name' => 'content[bento][library][body]', 'type' => 'textarea', 'rows' => 3, 'value' => $c['bento']['library']['body'], 'required' => true])
        @include('admin::cms.pages.forms._image', ['label' => 'Ảnh Library', 'prefix' => 'content[bento][library]', 'value' => $c['bento']['library'], 'aspect' => '4/3'])
    </section>

    <section class="space-y-4 py-6">
        <h4 class="font-label-md text-label-md text-on-surface">Bento — Path</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề', 'name' => 'content[bento][path][title]', 'value' => $c['bento']['path']['title'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Nội dung', 'name' => 'content[bento][path][body]', 'type' => 'textarea', 'rows' => 3, 'value' => $c['bento']['path']['body'], 'required' => true])
    </section>

    <section class="space-y-4 py-6">
        <h4 class="font-label-md text-label-md text-on-surface">Bento — Exam Sim</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề', 'name' => 'content[bento][exam_sim][title]', 'value' => $c['bento']['exam_sim']['title'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Nội dung', 'name' => 'content[bento][exam_sim][body]', 'type' => 'textarea', 'rows' => 3, 'value' => $c['bento']['exam_sim']['body'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Giá trị thống kê', 'name' => 'content[bento][exam_sim][stat_value]', 'value' => $c['bento']['exam_sim']['stat_value'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Nhãn thống kê', 'name' => 'content[bento][exam_sim][stat_label]', 'value' => $c['bento']['exam_sim']['stat_label'], 'required' => true])
    </section>

    <section class="space-y-4 pt-6">
        <h4 class="font-label-md text-label-md text-on-surface">Kêu gọi hành động (CTA)</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề', 'name' => 'content[cta][title]', 'value' => $c['cta']['title'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Mô tả', 'name' => 'content[cta][subtitle]', 'type' => 'textarea', 'rows' => 2, 'value' => $c['cta']['subtitle'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Nút chính', 'name' => 'content[cta][primary_label]', 'value' => $c['cta']['primary_label'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Ghi chú chân', 'name' => 'content[cta][footnote]', 'value' => $c['cta']['footnote'], 'required' => true])
    </section>
</div>
