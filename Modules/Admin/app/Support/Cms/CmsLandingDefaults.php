<?php

declare(strict_types=1);

namespace Modules\Admin\Support\Cms;

/** Structured defaults for Home + Features landing blocks. */
final class CmsLandingDefaults
{
    /**
     * @return array<string, mixed>
     */
    public static function home(string $app): array
    {
        return [
            'hero' => [
                'badge' => 'Hỗ trợ bởi AI dành riêng cho Y khoa',
                'title' => 'Học hiệu quả hơn — hiểu bản chất, nhớ lâu, luyện thi đúng trọng tâm',
                'title_highlight' => 'nhớ lâu',
                'subtitle' => 'Nền tảng ôn thi y khoa tiên tiến giúp sinh viên y khoa và bác sĩ trẻ chinh phục mọi kỳ thi từ cấp chứng chỉ hành nghề đến sau đại học.',
                'primary_cta_label' => 'Bắt đầu luyện thi',
                'secondary_cta_label' => 'Xem câu hỏi mẫu',
                'image_url' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCKmBmH_jL81lnqycVaK7f31yR9s5DTmfe_R2ff-ghVles69zerPs9sdP-MIydPlBjOzz3xNtDX4i7Xf_G-dEmflYTVaCsjIdt2rv6lxVYQuQV3djPPfuMypgCd64GAuYtWUzndhQqEqKc8nZ7mGL1bFttR4pjQ1KxIhCUy8VST6R_epo77FoVfyej2PcVFNEzIo8NnR8BGyXeUkGvk19evM_UetyDHbHLFjTbkcll_UXpL-UBwn1IXXt6pv9fLYKftT5evadVYlZPC',
                'image_alt' => "Giao diện bảng điều khiển học tập y khoa {$app}",
            ],
            'stats' => [
                'items' => [
                    ['value' => '12.450', 'label' => 'Câu hỏi chuẩn hóa'],
                    ['value' => '38.000+', 'label' => 'Người học tin dùng'],
                    ['value' => '18', 'label' => 'Chuyên ngành y khoa'],
                ],
            ],
            'values' => [
                'items' => [
                    ['title' => 'Ngân hàng câu hỏi chuẩn hóa', 'description' => 'Được biên soạn bởi các chuyên gia đầu ngành, bám sát cấu trúc đề thi thực tế mới nhất.'],
                    ['title' => 'Luyện đề thông minh & điểm yếu', 'description' => 'Thuật toán Adaptive Learning giúp phát hiện và tập trung vào các lỗ hổng kiến thức của bạn.'],
                    ['title' => 'AI Tutor giải thích tận gốc', 'description' => 'Trợ lý ảo y khoa sẵn sàng giải thích từng cơ chế bệnh sinh, không chỉ đơn thuần là đưa ra đáp án.'],
                ],
            ],
            'feature_blocks' => [
                'items' => [
                    [
                        'eyebrow' => 'Qbank Chuyên Sâu',
                        'title' => 'Học từ những tình huống lâm sàng thực tế',
                        'body' => 'Mỗi câu hỏi là một ca lâm sàng mô phỏng sát với thực tiễn bệnh viện, giúp bạn rèn luyện tư duy chẩn đoán và xử trí đúng phác đồ.',
                        'bullets' => ['Giải thích chi tiết từng đáp án nhiễu', 'Cập nhật theo Guidelines mới nhất (AHA, ESC, ADA...)'],
                        'image_url' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBco-i_0cAFlAdsl6M2zaXtQz-AjP0tob5jmAwlVHZV34ooZqquhQy23_ahzFgZA6pPyYduvqwF2Z7txT5jFFmgfsKwZEPC7DlF0cCeMISnymOUebq9o-vWXNFeBb_go5v_eUXp67rUTM98wxDVzWmZRk-mhyWe-llPO2ZKCG6SNFXWB-eLHltGjrIbDGoK05U97-Fwof9VFH9AYhoKncqTT2nmUaXaeZUnted0jv3Xvc2o7_DLktjKU8S1xBPRAlVA_t8guvdx6LGH',
                        'image_alt' => "Giao diện ngân hàng câu hỏi {$app}",
                    ],
                    [
                        'eyebrow' => 'Thư viện số',
                        'title' => 'Mọi tài liệu bạn cần trong một tầm tay',
                        'body' => 'MedLib tích hợp hàng nghìn đầu sách y khoa và bài báo khoa học uy tín, được tổ chức thông minh để bạn tra cứu nhanh chóng trong lúc làm bài.',
                        'mini_cards' => [
                            ['title' => 'Search AI', 'description' => 'Tìm kiếm chính xác từ khóa trong nội dung sách.'],
                            ['title' => 'Sync All', 'description' => 'Đồng bộ ghi chú trên mọi thiết bị di động.'],
                        ],
                        'image_url' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBDnGjQ_o0IjKRjvJ1yKV48HHY4vxexg6Vrc9eo1KrGZHD0__BHgFrertnUrND8XwRD8ZgLNbCDaDkvEGmQkSS2AVRaeGWzNFfXrZh9qP-V4D8TPkIbvVlecqTqOsodMhO02hnvVp38Ee6Dbi08N7VAexRLrXkGraZPDie9njNzkonEgyqQgOCINd_1C4w-VviMq_28lETwm1uG18OVIwhX47_9rXfJYg0KQMqh9UmITXJSvp1QSvypuMCTCDpVFyH9WmHtZ-kt1wSw',
                        'image_alt' => 'Thư viện số MedLib trên máy tính bảng',
                    ],
                    [
                        'eyebrow' => 'AI Assistant',
                        'title' => 'Hỏi bất cứ điều gì, trả lời tức thì',
                        'body' => 'MedAI không chỉ là một chatbot, đó là một chuyên gia y tế được huấn luyện trên hàng triệu dữ liệu lâm sàng để hỗ trợ bạn học tập và tra cứu lâm sàng.',
                        'chat_user' => 'Giải thích cơ chế cơn đau thắt ngực không ổn định?',
                        'chat_ai' => 'Cơn đau thắt ngực không ổn định thường do sự nứt vỡ mảng xơ vữa dẫn đến hình thành huyết khối không hoàn toàn...',
                        'image_url' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCMZgidLBFyHnk0vrswjJRjSr-4vrn5qUOhjp-K_ae3Z0i0u0WgIANj0aQeDOr146w66qsdxvCDNmqSwFpcRzBkfZKdGCQzurFCR1kiWJ2i8G7xUFOaOtr0Z5fWErXh_pdRwRimRo9kUR_gUXk4ZVGJxOz6I05xE8oGZwA9aPAqMbZQpNFQ2PR-Ecq8zneZ9CDpzIPV2uaPGIW4L3yG8h2Clj16aQjej6puVrcjURyHpoLOC5jqkgOot7FtHFuxgLpiBrA9gJRu2eYV',
                        'image_alt' => 'Trợ lý AI phân tích mô hình giải phẫu tim',
                    ],
                    [
                        'eyebrow' => 'Phân tích học tập',
                        'title' => 'Theo dõi tiến độ, tối ưu hiệu suất',
                        'body' => 'Biểu đồ hóa kỹ năng của bạn qua từng chương học. Biết chính xác bạn đang yếu ở Nội khoa hay Ngoại khoa để điều chỉnh chiến lược ôn tập.',
                        'metrics' => [
                            ['value' => '96%', 'label' => 'Độ chính xác'],
                            ['value' => '24h', 'label' => 'Thời gian học/tuần'],
                        ],
                        'image_url' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDMRt350QoWPMlsBSNXmCbfOmNEV8u97JDBrRf1gFyKu0L-xhboxI2kTFZuB9nHsezRjCwrB3q0kTshAM7Ujj5-DhmesvqpZWSGKHIxVU4r0IMSG5FaE-dSDmL13Mm_WYqlocAoc1yGbS9xCYuGYMQ6s4TDh-kuuBiOHjTskPPilYxvUHBNBNE9cjXhOVX7qfSrI66F9hDqQzahTRNBevPcIn70iNG_ph9TpzI5qJ4iRqqw6ithf_iS3t2HraKAUz43w0QPS2fuybVp',
                        'image_alt' => 'Bảng phân tích học tập',
                    ],
                ],
            ],
            'testimonials' => [
                'heading' => "Học viên nói gì về {$app}?",
                'subtitle' => 'Hàng ngàn bác sĩ tương lai đã bứt phá điểm số nhờ lộ trình ôn tập khoa học.',
                'items' => [
                    ['name' => 'Nguyễn Văn An', 'role' => 'Sinh viên Y6 - ĐH Y Hà Nội', 'quote' => "Ngân hàng câu hỏi cực kỳ sát đề thi thực tế. Mình đã đậu CCHN ngay lần thi đầu tiên nhờ {$app}.", 'image_url' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDfJg-HhjBan8vIVWivC-GW2WvRhJjoAB3kkPut6VHyMAFXlCeR7wuFKc6Olip5imqRDGySZOjwLxRSV5GJD9SUdePEQg3FEZI5QnYg9c8vIsNKeJStAEz4m8cX4WP7KjKEdI57wKhqo9tt0--NpJZ-4BXTm-FcWHWBRDjl28231rHEuSIN4gQjKm-l3Yv8Fm9VPw9VTb1TqTu13WKzKbUqcOUj0v2dm_zOfDVQ0_vWdG8JVEL_RR2cvDsK17pKR_nupL2iocS9U6Tj'],
                    ['name' => 'Trần Thị Mai', 'role' => 'Bác sĩ nội trú Nhi khoa', 'quote' => 'Phần AI giải thích rất dễ hiểu, giúp mình nắm chắc bản chất thay vì chỉ học thuộc lòng đáp án.', 'image_url' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBgsqfba9CocTxf8S17nTmdHvgMW_D4nNi6GhaPw3TeVaHo0tYEfgPFEZ8tJycKMX2ohBriXUW_urq6Ev9igFK9YCAmMs0i9cX2qPkLPjbwWnnBg3PM9Pn1zTarygbh8M9AmgudA2LY847vJrPhiSVERpzuPt1zQ8As2P_bnlf-ZiEYVj52rKJ6eIKmJnfuJ1zdwJdp-TutHlAwaBIv1jjR02-BzL1R9MK_HV3-LiEf274xXsb_cqrTBxq4fEzxx_LrBTn6E6FA4cGz'],
                    ['name' => 'Lê Minh Đức', 'role' => 'Bác sĩ đa khoa', 'quote' => 'Giao diện đẹp, mượt mà trên cả điện thoại. Mình có thể tranh thủ ôn bài mọi lúc mọi nơi.', 'image_url' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDh0oAVpsp-78XhhSIT9Tq81R3zdNwGRPFrh8ee8yBAmQT_I6mmFXAA2o4odu0F8G7Bsz8Ogaxj9Y6IaSYo4lS2cbw4kZv7Z6ReRckRYmZTVikPfwxcUac2H1ywvC0cA_ixxUntjBLXsYQVPBC4iRX4DFD4kUZgiv04NMYbeeVvAAKdxpaRVOojAkscY7YkdmrMbRO46Nsb8m4fhbQlPRnwanjaDbAJWKMtSevjSOMgxComsIthL6xmgivq_90bfPr0Wfw-4IvXHYtD'],
                ],
            ],
            'pricing' => [
                'heading' => 'Lựa chọn gói phù hợp',
                'subtitle' => 'Đầu tư vào kiến thức là khoản đầu tư có lãi nhất.',
                'free' => [
                    'name' => 'Miễn phí',
                    'description' => 'Cơ bản cho người mới bắt đầu',
                    'cta_label' => 'Bắt đầu ngay',
                    'features_included' => ['20 câu hỏi/ngày', 'Thư viện giới hạn'],
                    'features_excluded' => ['AI không giới hạn', 'Toàn bộ QBank'],
                ],
                'premium_yearly' => [
                    'description' => 'Giải pháp ôn thi toàn diện · thanh toán một lần',
                    'cta_label_prefix' => 'Mua gói',
                    'features' => ['Toàn bộ QBank & Thư viện', 'AI Mentor không giới hạn', 'Mô phỏng thi thật', 'Phân tích lỗ hổng kiến thức', 'Ưu tiên hỗ trợ 24/7'],
                ],
                'premium_monthly' => [
                    'name' => 'Premium 1 tháng',
                    'description' => 'Linh hoạt theo từng giai đoạn',
                    'note' => 'Không cam kết dài hạn · có thể nâng cấp sang gói năm bất cứ lúc nào',
                    'cta_label' => 'Nâng cấp theo tháng',
                    'features' => ['Toàn bộ QBank', 'Thư viện đầy đủ', 'AI không giới hạn', 'Phân tích nâng cao'],
                ],
                'detail_link_label' => 'Xem bảng giá chi tiết',
            ],
            'faq' => [
                'heading' => 'Câu hỏi thường gặp',
                'items' => [
                    ['question' => "{$app} có hỗ trợ ôn thi Nội trú không?", 'answer' => "Có, {$app} có ngân hàng đề thi dành riêng cho kỳ thi Sau đại học bao gồm Nội trú, Cao học và Chuyên khoa 1 với mức độ khó và phân hóa cao."],
                    ['question' => 'Dữ liệu câu hỏi được lấy từ đâu?', 'answer' => "Hệ thống câu hỏi được đội ngũ bác sĩ nội trú và giảng viên y khoa biên soạn dựa trên các textbook chuẩn như Harrison's, Gray's Anatomy, Sabiston... và các đề thi thật qua các năm."],
                    ['question' => 'Tôi có thể học trên điện thoại không?', 'answer' => "Hoàn toàn có thể. {$app} có phiên bản website mobile mượt mà và ứng dụng trên App Store/Google Play giúp bạn ôn tập mọi lúc mọi nơi."],
                    ['question' => 'Chính sách hoàn tiền như thế nào?', 'answer' => 'Chúng tôi cam kết hoàn tiền 100% trong vòng 7 ngày nếu bạn không hài lòng với chất lượng dịch vụ mà không cần lý do.'],
                    ['question' => 'Hệ thống AI có thể giải đáp các thắc mắc khó không?', 'answer' => 'MedAI được tối ưu cho các câu hỏi về cơ chế bệnh sinh, chẩn đoán phân biệt và xử trí lâm sàng theo hướng dẫn (guidelines). Tuy nhiên, bạn nên luôn đối chiếu với y văn chính thống.'],
                ],
            ],
            'cta' => [
                'title' => 'Sẵn sàng chinh phục kỳ thi?',
                'subtitle' => "Gia nhập cộng đồng hơn 38.000 y bác sĩ đang sử dụng {$app} để nâng tầm kiến thức mỗi ngày.",
                'primary_label' => 'Đăng ký miễn phí',
                'secondary_label' => 'Liên hệ hỗ trợ',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function features(string $app): array
    {
        return [
            'hero' => [
                'title' => 'Đột phá kết quả học tập với bộ công cụ Y khoa toàn diện',
                'subtitle' => "{$app} kết hợp trí tuệ nhân tạo và phương pháp học tập khoa học giúp sinh viên y khoa và bác sĩ trẻ tối ưu hóa thời gian ôn luyện.",
                'primary_cta_label' => 'Dùng thử miễn phí',
                'secondary_cta_label' => 'Xem video hướng dẫn',
                'video_url' => '#',
            ],
            'bento' => [
                'qbank' => [
                    'title' => 'Ngân hàng câu hỏi (QBank)',
                    'body' => 'Hàng ngàn câu hỏi chuẩn hóa kèm giải thích chi tiết, cập nhật theo phác đồ mới nhất từ các nguồn uy tín toàn cầu.',
                    'tags' => ['#USMLE', '#NộiKhoa', '#NgoạiKhoa'],
                    'image_url' => 'https://lh3.googleusercontent.com/aida/AP1WRLv_p2rdQZm0QQ4HvbOfjWopS8En2e5kGHO6v7tyXV3e0jRVKf5mWIcMZc5_oq8ifygyXE5b7z672n2t_r74rpWb4TDXrHyM7iKQSkSlnUJdXgJoq6OE4tprXxLOP0gxlo2YJtAemTUVE3g03K1IgJ_25DBeV9a9anSstVhbpiLRASFVWDnT9UedXkXeGddCz1blvvS5VY3Yh4NyV09frO4ywaIMeH0HGe-veAamQR8wvchErRMU7KYFjDM',
                    'image_alt' => 'Giao diện QBank',
                ],
                'study_exam' => [
                    'title' => 'Chế độ Study/Exam',
                    'body' => 'Linh hoạt giữa việc học sâu không áp lực thời gian và chế độ thi thử mô phỏng thực tế để rèn luyện tâm lý.',
                ],
                'flashcards' => [
                    'title' => 'Flashcards Spaced Repetition',
                    'body' => 'Thuật toán lặp lại ngắt quãng (SRS) thông minh giúp tối ưu hóa việc ghi nhớ kiến thức dài hạn, chống lại đường cong quên lãng.',
                ],
                'ai_tutor' => [
                    'title' => 'AI Tutor Thông Minh',
                    'body' => 'Giải thích cơ chế bệnh sinh phức tạp, phân tích case lâm sàng và trả lời thắc mắc tức thì như một trợ giảng chuyên khoa 24/7.',
                    'cta_label' => 'Trải nghiệm ngay',
                    'image_url' => 'https://lh3.googleusercontent.com/aida/AP1WRLtbNmWPQx0hanFuFxyjoLB6UuYj8xfD2PrSKgG6y03TG-muRS6Ok3IzkXZGcFVpnyYHPYtLMSYxz7zj0LWIyfxSchUkzFMZjl5S0luGeUiUPdWq8nOf2_obIgrnivwAfpWXjH6hn57WgKqrDicTEt-Di6Lak7jpNiRA3cUPENMQVy1PjuWJ45M13ahCy72-05ljEzvhmLbd3Lv029xyui5gBtt3WivCKiJNGYuif2mxdCuLQ6dPKlltirM',
                    'image_alt' => 'Mô hình giải phẫu AI',
                ],
                'analytics' => [
                    'title' => 'Phân tích & Heatmap',
                    'badge' => '+24% hiệu quả',
                    'body' => 'Theo dõi tiến độ học tập trực quan qua biểu đồ nhiệt và dự đoán khả năng đỗ kỳ thi dựa trên dữ liệu hiệu suất cá nhân.',
                    'image_url' => 'https://lh3.googleusercontent.com/aida/AP1WRLstF7jgbA9N0LiPoCV0AG4CueRmU_QaD2_V6JFv2o60GXvhyo0qCqwgWZ6oBxCvU1PsgRFh22SkTEbXekugIPhUz_yJsBcLe6gW-_1R7bVwj643mKNwGexQ2shVQT4sO_tOWXg3rcCCd-uM8hpht0nlW1aSWN0XOUgxmHvNaBrJbVJ7TD9822SZCtlkBU3FN2VPIUdHt4wRw76HwoKZyMTxrZzPt8MfMtGHLdbr-6nvJkaHEJbudnhXv-j0',
                    'image_alt' => 'Bảng phân tích',
                ],
                'library' => [
                    'title' => 'Thư viện liên kết chéo',
                    'body' => 'Tra cứu ngay các thuật ngữ, giải phẫu và tài liệu liên quan trong khi làm bài mà không cần phải rời khỏi màn hình ôn luyện.',
                    'image_url' => 'https://lh3.googleusercontent.com/aida/AP1WRLsP1dQbJrTKbdKx7DhFaFDyG_BXJjiYS1f2xravLRoVLpXo5oJqnIWdbsVLIqOXxj-243qHayEF7BQbioFC5MJsD7b12tVCMwQz9Zqa2rWLdfan4JggLdfvjFSrFx-FvA5NI66GY9kalnp1G7BqQlDlO_FU33ENhv-aYtnZAOPRi-z-Zttp0S5LQbUnF0um-0I6jJtm5-nu9JPSNZB3o28Ig30vNaTWBiy50U5rJSXRo1bqic4y9rdVHqI',
                    'image_alt' => 'Thư viện y khoa',
                ],
                'path' => [
                    'title' => 'Lộ trình cá nhân hóa',
                    'body' => 'Thuật toán tự động đề xuất nội dung ôn tập trọng tâm dựa trên các lỗ hổng kiến thức được hệ thống phát hiện qua quá trình làm bài.',
                ],
                'exam_sim' => [
                    'title' => 'Mô phỏng thi thật',
                    'body' => 'Giao diện, áp lực thời gian và quy trình sát 99% so với các kỳ thi Nội trú, USMLE và chứng chỉ hành nghề thực tế.',
                    'stat_value' => '99%',
                    'stat_label' => 'Tương đồng',
                ],
            ],
            'cta' => [
                'title' => 'Sẵn sàng chinh phục kỳ thi Y khoa?',
                'subtitle' => "Tham gia cùng hơn 50.000 sinh viên và bác sĩ đang nâng tầm kiến thức mỗi ngày cùng {$app}.",
                'primary_label' => 'Đăng ký tài khoản miễn phí',
                'footnote' => 'Bắt đầu ngay, không yêu cầu thẻ tín dụng.',
            ],
        ];
    }
}
