@php
    $c = $content;
@endphp

<div class="divide-y divide-outline-variant/70">
    <section class="space-y-4 pb-6">
        <h4 class="font-label-md text-label-md text-on-surface">Hero</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Badge', 'name' => 'content[hero][badge]', 'value' => $c['hero']['badge'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề', 'name' => 'content[hero][title]', 'value' => $c['hero']['title'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Phần highlight tiêu đề', 'name' => 'content[hero][title_highlight]', 'value' => $c['hero']['title_highlight'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Mô tả ngắn', 'name' => 'content[hero][subtitle]', 'type' => 'textarea', 'rows' => 2, 'value' => $c['hero']['subtitle'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Nút chính', 'name' => 'content[hero][primary_cta_label]', 'value' => $c['hero']['primary_cta_label'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Nút phụ', 'name' => 'content[hero][secondary_cta_label]', 'value' => $c['hero']['secondary_cta_label'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'URL ảnh', 'name' => 'content[hero][image_url]', 'type' => 'url', 'value' => $c['hero']['image_url'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Mô tả ảnh (alt)', 'name' => 'content[hero][image_alt]', 'value' => $c['hero']['image_alt'], 'required' => true])
    </section>

    <section class="space-y-4 py-6">
        <h4 class="font-label-md text-label-md text-on-surface">Thống kê</h4>
        @foreach ($c['stats']['items'] as $index => $stat)
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @include('admin::cms.pages.forms._field', ['label' => 'Số liệu #'.($index + 1), 'name' => "content[stats][items][{$index}][value]", 'value' => $stat['value'], 'required' => true])
                @include('admin::cms.pages.forms._field', ['label' => 'Nhãn #'.($index + 1), 'name' => "content[stats][items][{$index}][label]", 'value' => $stat['label'], 'required' => true])
            </div>
        @endforeach
    </section>

    <section class="space-y-4 py-6">
        <h4 class="font-label-md text-label-md text-on-surface">Giá trị nổi bật</h4>
        @foreach ($c['values']['items'] as $index => $item)
            <div class="space-y-3 rounded-lg bg-surface-container-lowest p-4">
                <p class="font-label-sm text-on-surface-variant">Giá trị #{{ $index + 1 }}</p>
                @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề', 'name' => "content[values][items][{$index}][title]", 'value' => $item['title'], 'required' => true])
                @include('admin::cms.pages.forms._field', ['label' => 'Mô tả', 'name' => "content[values][items][{$index}][description]", 'type' => 'textarea', 'rows' => 2, 'value' => $item['description'], 'required' => true])
            </div>
        @endforeach
    </section>

    <section class="space-y-4 py-6">
        <h4 class="font-label-md text-label-md text-on-surface">Khối tính năng</h4>
        @foreach ($c['feature_blocks']['items'] as $index => $block)
            <div class="space-y-3 rounded-lg bg-surface-container-lowest p-4">
                <p class="font-label-sm text-on-surface-variant">Khối #{{ $index + 1 }}</p>
                @include('admin::cms.pages.forms._field', ['label' => 'Eyebrow', 'name' => "content[feature_blocks][items][{$index}][eyebrow]", 'value' => $block['eyebrow'], 'required' => true])
                @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề', 'name' => "content[feature_blocks][items][{$index}][title]", 'value' => $block['title'], 'required' => true])
                @include('admin::cms.pages.forms._field', ['label' => 'Nội dung', 'name' => "content[feature_blocks][items][{$index}][body]", 'type' => 'textarea', 'rows' => 3, 'value' => $block['body'], 'required' => true])
                @include('admin::cms.pages.forms._field', ['label' => 'URL ảnh', 'name' => "content[feature_blocks][items][{$index}][image_url]", 'type' => 'url', 'value' => $block['image_url'], 'required' => true])
                @include('admin::cms.pages.forms._field', ['label' => 'Mô tả ảnh (alt)', 'name' => "content[feature_blocks][items][{$index}][image_alt]", 'value' => $block['image_alt'], 'required' => true])

                @if ($index === 0)
                    @foreach ($block['bullets'] as $bulletIndex => $bullet)
                        @include('admin::cms.pages.forms._field', ['label' => 'Bullet #'.($bulletIndex + 1), 'name' => "content[feature_blocks][items][{$index}][bullets][{$bulletIndex}]", 'value' => $bullet, 'required' => true])
                    @endforeach
                @elseif ($index === 1)
                    @foreach ($block['mini_cards'] as $cardIndex => $card)
                        <div class="space-y-3 rounded-lg border border-outline-variant/40 p-3">
                            <p class="font-label-sm text-on-surface-variant">Mini card #{{ $cardIndex + 1 }}</p>
                            @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề', 'name' => "content[feature_blocks][items][{$index}][mini_cards][{$cardIndex}][title]", 'value' => $card['title'], 'required' => true])
                            @include('admin::cms.pages.forms._field', ['label' => 'Mô tả', 'name' => "content[feature_blocks][items][{$index}][mini_cards][{$cardIndex}][description]", 'type' => 'textarea', 'rows' => 2, 'value' => $card['description'], 'required' => true])
                        </div>
                    @endforeach
                @elseif ($index === 2)
                    @include('admin::cms.pages.forms._field', ['label' => 'Chat người dùng', 'name' => "content[feature_blocks][items][{$index}][chat_user]", 'type' => 'textarea', 'rows' => 2, 'value' => $block['chat_user'], 'required' => true])
                    @include('admin::cms.pages.forms._field', ['label' => 'Chat AI', 'name' => "content[feature_blocks][items][{$index}][chat_ai]", 'type' => 'textarea', 'rows' => 3, 'value' => $block['chat_ai'], 'required' => true])
                @elseif ($index === 3)
                    @foreach ($block['metrics'] as $metricIndex => $metric)
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @include('admin::cms.pages.forms._field', ['label' => 'Metric #'.($metricIndex + 1).' — giá trị', 'name' => "content[feature_blocks][items][{$index}][metrics][{$metricIndex}][value]", 'value' => $metric['value'], 'required' => true])
                            @include('admin::cms.pages.forms._field', ['label' => 'Metric #'.($metricIndex + 1).' — nhãn', 'name' => "content[feature_blocks][items][{$index}][metrics][{$metricIndex}][label]", 'value' => $metric['label'], 'required' => true])
                        </div>
                    @endforeach
                @endif
            </div>
        @endforeach
    </section>

    <section class="space-y-4 py-6">
        <h4 class="font-label-md text-label-md text-on-surface">Đánh giá học viên</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề section', 'name' => 'content[testimonials][heading]', 'value' => $c['testimonials']['heading'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Mô tả phụ', 'name' => 'content[testimonials][subtitle]', 'type' => 'textarea', 'rows' => 2, 'value' => $c['testimonials']['subtitle'], 'required' => true])
        @foreach ($c['testimonials']['items'] as $index => $item)
            <div class="space-y-3 rounded-lg bg-surface-container-lowest p-4">
                <p class="font-label-sm text-on-surface-variant">Đánh giá #{{ $index + 1 }}</p>
                @include('admin::cms.pages.forms._field', ['label' => 'Họ tên', 'name' => "content[testimonials][items][{$index}][name]", 'value' => $item['name'], 'required' => true])
                @include('admin::cms.pages.forms._field', ['label' => 'Vai trò', 'name' => "content[testimonials][items][{$index}][role]", 'value' => $item['role'], 'required' => true])
                @include('admin::cms.pages.forms._field', ['label' => 'Trích dẫn', 'name' => "content[testimonials][items][{$index}][quote]", 'type' => 'textarea', 'rows' => 3, 'value' => $item['quote'], 'required' => true])
                @include('admin::cms.pages.forms._field', ['label' => 'URL ảnh', 'name' => "content[testimonials][items][{$index}][image_url]", 'type' => 'url', 'value' => $item['image_url'], 'required' => true])
            </div>
        @endforeach
    </section>

    <section class="space-y-4 py-6">
        <h4 class="font-label-md text-label-md text-on-surface">Bảng giá</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề section', 'name' => 'content[pricing][heading]', 'value' => $c['pricing']['heading'], 'required' => true])
        @include('admin::cms.pages.forms._field', ['label' => 'Mô tả phụ', 'name' => 'content[pricing][subtitle]', 'type' => 'textarea', 'rows' => 2, 'value' => $c['pricing']['subtitle'], 'required' => true])

        <div class="space-y-3 rounded-lg bg-surface-container-lowest p-4">
            <p class="font-label-sm text-on-surface-variant">Gói miễn phí</p>
            @include('admin::cms.pages.forms._field', ['label' => 'Tên gói', 'name' => 'content[pricing][free][name]', 'value' => $c['pricing']['free']['name'], 'required' => true])
            @include('admin::cms.pages.forms._field', ['label' => 'Mô tả', 'name' => 'content[pricing][free][description]', 'value' => $c['pricing']['free']['description'], 'required' => true])
            @include('admin::cms.pages.forms._field', ['label' => 'Nút CTA', 'name' => 'content[pricing][free][cta_label]', 'value' => $c['pricing']['free']['cta_label'], 'required' => true])
            @foreach ($c['pricing']['free']['features_included'] as $index => $feature)
                @include('admin::cms.pages.forms._field', ['label' => 'Tính năng có #'.($index + 1), 'name' => "content[pricing][free][features_included][{$index}]", 'value' => $feature, 'required' => true])
            @endforeach
            @foreach ($c['pricing']['free']['features_excluded'] as $index => $feature)
                @include('admin::cms.pages.forms._field', ['label' => 'Tính năng không có #'.($index + 1), 'name' => "content[pricing][free][features_excluded][{$index}]", 'value' => $feature, 'required' => true])
            @endforeach
        </div>

        <div class="space-y-3 rounded-lg bg-surface-container-lowest p-4">
            <p class="font-label-sm text-on-surface-variant">Premium năm</p>
            @include('admin::cms.pages.forms._field', ['label' => 'Mô tả', 'name' => 'content[pricing][premium_yearly][description]', 'value' => $c['pricing']['premium_yearly']['description'], 'required' => true])
            @include('admin::cms.pages.forms._field', ['label' => 'Tiền tố nút CTA', 'name' => 'content[pricing][premium_yearly][cta_label_prefix]', 'value' => $c['pricing']['premium_yearly']['cta_label_prefix'], 'required' => true])
            @foreach ($c['pricing']['premium_yearly']['features'] as $index => $feature)
                @include('admin::cms.pages.forms._field', ['label' => 'Tính năng #'.($index + 1), 'name' => "content[pricing][premium_yearly][features][{$index}]", 'value' => $feature, 'required' => true])
            @endforeach
        </div>

        <div class="space-y-3 rounded-lg bg-surface-container-lowest p-4">
            <p class="font-label-sm text-on-surface-variant">Premium tháng</p>
            @include('admin::cms.pages.forms._field', ['label' => 'Tên gói', 'name' => 'content[pricing][premium_monthly][name]', 'value' => $c['pricing']['premium_monthly']['name'], 'required' => true])
            @include('admin::cms.pages.forms._field', ['label' => 'Mô tả', 'name' => 'content[pricing][premium_monthly][description]', 'value' => $c['pricing']['premium_monthly']['description'], 'required' => true])
            @include('admin::cms.pages.forms._field', ['label' => 'Ghi chú', 'name' => 'content[pricing][premium_monthly][note]', 'type' => 'textarea', 'rows' => 2, 'value' => $c['pricing']['premium_monthly']['note'], 'required' => true])
            @include('admin::cms.pages.forms._field', ['label' => 'Nút CTA', 'name' => 'content[pricing][premium_monthly][cta_label]', 'value' => $c['pricing']['premium_monthly']['cta_label'], 'required' => true])
            @foreach ($c['pricing']['premium_monthly']['features'] as $index => $feature)
                @include('admin::cms.pages.forms._field', ['label' => 'Tính năng #'.($index + 1), 'name' => "content[pricing][premium_monthly][features][{$index}]", 'value' => $feature, 'required' => true])
            @endforeach
        </div>

        @include('admin::cms.pages.forms._field', ['label' => 'Nhãn link chi tiết', 'name' => 'content[pricing][detail_link_label]', 'value' => $c['pricing']['detail_link_label'], 'required' => true])
    </section>

    <section class="space-y-4 py-6">
        <h4 class="font-label-md text-label-md text-on-surface">FAQ</h4>
        @include('admin::cms.pages.forms._field', ['label' => 'Tiêu đề section', 'name' => 'content[faq][heading]', 'value' => $c['faq']['heading'], 'required' => true])
        @foreach ($c['faq']['items'] as $index => $item)
            <div class="space-y-3 rounded-lg bg-surface-container-lowest p-4">
                <p class="font-label-sm text-on-surface-variant">Câu hỏi #{{ $index + 1 }}</p>
                @include('admin::cms.pages.forms._field', ['label' => 'Câu hỏi', 'name' => "content[faq][items][{$index}][question]", 'value' => $item['question'], 'required' => true])
                @include('admin::cms.pages.forms._field', ['label' => 'Trả lời', 'name' => "content[faq][items][{$index}][answer]", 'type' => 'textarea', 'rows' => 3, 'value' => $item['answer'], 'required' => true])
            </div>
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
