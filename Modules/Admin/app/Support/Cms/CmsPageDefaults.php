<?php

declare(strict_types=1);

namespace Modules\Admin\Support\Cms;

use Modules\Admin\Support\Enums\CmsPageKey;

final class CmsPageDefaults
{
    /**
     * @return array<string, mixed>
     */
    public static function for(CmsPageKey $key, ?string $appName = null): array
    {
        $app = $appName ?? (string) config('app.name');

        return match ($key) {
            CmsPageKey::Home => CmsLandingDefaults::home($app),
            CmsPageKey::Features => CmsLandingDefaults::features($app),
            CmsPageKey::About => self::about($app),
            CmsPageKey::Contact => self::contact($app),
            CmsPageKey::Terms => self::legalPage(
                intro: "Cập nhật lần cuối: 14/08/2026. Bằng việc truy cập và sử dụng {$app}, bạn đồng ý tuân thủ các điều khoản dưới đây.",
                sections: [
                    ['title' => '1. Phạm vi dịch vụ', 'body' => "{$app} cung cấp nền tảng học tập trực tuyến dành cho sinh viên và nhân viên y tế, bao gồm ngân hàng câu hỏi, thư viện y khoa, lộ trình học và các công cụ hỗ trợ ôn thi. Nội dung mang tính tham khảo giáo dục, không thay thế tư vấn y khoa trực tiếp."],
                    ['title' => '2. Tài khoản người dùng', 'body' => 'Bạn chịu trách nhiệm bảo mật thông tin đăng nhập và mọi hoạt động phát sinh từ tài khoản của mình. Không chia sẻ tài khoản cho bên thứ ba. Chúng tôi có quyền tạm khóa hoặc chấm dứt tài khoản vi phạm điều khoản.'],
                    ['title' => '3. Gói dịch vụ & thanh toán', 'body' => 'Gói Free và Premium được mô tả tại trang Bảng giá. Phí gói trả phí có thể thay đổi theo thông báo trước trên website. Chính sách hoàn tiền (nếu có) được quy định riêng tại thời điểm thanh toán.'],
                    ['title' => '4. Quyền sở hữu trí tuệ', 'body' => "Toàn bộ nội dung trên {$app} (câu hỏi, bài giảng, giao diện, nhãn hiệu) thuộc quyền sở hữu của chúng tôi hoặc đối tác cấp phép. Nghiêm cấm sao chép, phân phối lại hoặc khai thác thương mại khi chưa có sự đồng ý bằng văn bản."],
                    ['title' => '5. Giới hạn trách nhiệm', 'body' => "Chúng tôi nỗ lực duy trì dịch vụ ổn định nhưng không đảm bảo không gián đoạn. {$app} không chịu trách nhiệm về quyết định lâm sàng hay kết quả thi chỉ dựa trên nội dung học tập trên nền tảng."],
                    ['title' => '6. Liên hệ', 'body' => 'Mọi thắc mắc về điều khoản, vui lòng liên hệ hotro@medpro.vn hoặc qua trang Liên hệ.'],
                ],
            ),
            CmsPageKey::Privacy => self::legalPage(
                intro: "Cập nhật lần cuối: 14/08/2026. {$app} cam kết bảo vệ quyền riêng tư và dữ liệu cá nhân của người dùng theo quy định pháp luật Việt Nam.",
                sections: [
                    ['title' => '1. Dữ liệu thu thập', 'body' => 'Chúng tôi có thể thu thập: họ tên, email, thông tin đăng nhập, lịch sử học tập, thiết bị/trình duyệt, cookie phân tích và dữ liệu thanh toán (qua đối tác cổng thanh toán — không lưu trữ đầy đủ thông tin thẻ).'],
                    ['title' => '2. Mục đích sử dụng', 'body' => "Vận hành tài khoản và cung cấp dịch vụ học tập.\nCá nhân hóa lộ trình, thống kê tiến độ và gợi ý nội dung.\nHỗ trợ khách hàng, thông báo cập nhật sản phẩm (khi bạn đồng ý).\nPhòng chống gian lận và đảm bảo an toàn hệ thống."],
                    ['title' => '3. Chia sẻ dữ liệu', 'body' => 'Chúng tôi không bán dữ liệu cá nhân. Dữ liệu chỉ được chia sẻ với nhà cung cấp hạ tầng (hosting, email, thanh toán) trong phạm vi cần thiết và có cam kết bảo mật, hoặc khi pháp luật yêu cầu.'],
                    ['title' => '4. Lưu trữ & bảo mật', 'body' => 'Dữ liệu được lưu trên máy chủ bảo mật, mã hóa truyền tải (HTTPS) và phân quyền truy cập nội bộ. Thời gian lưu trữ phù hợp với mục đích dịch vụ hoặc theo yêu cầu pháp lý.'],
                    ['title' => '5. Quyền của bạn', 'body' => 'Bạn có thể yêu cầu truy cập, chỉnh sửa hoặc xóa dữ liệu cá nhân qua trang Cài đặt tài khoản hoặc liên hệ hotro@medpro.vn. Bạn có thể từ chối cookie không thiết yếu qua banner Cookie trên website.'],
                    ['title' => '6. Thay đổi chính sách', 'body' => "Chúng tôi có thể cập nhật chính sách này và thông báo trên website. Việc tiếp tục sử dụng {$app} sau khi cập nhật đồng nghĩa bạn chấp nhận phiên bản mới."],
                ],
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function about(string $app): array
    {
        return [
            'hero' => [
                'title' => "Sứ mệnh của {$app}",
                'subtitle' => 'Giúp mọi sinh viên & bác sĩ Y khoa Việt Nam học hiệu quả, thi tự tin với nền tảng công nghệ giáo dục hiện đại nhất.',
            ],
            'story' => [
                'heading' => 'Câu chuyện ra đời',
                'paragraph_1' => "{$app} được khởi xướng bởi một nhóm các bác sĩ trẻ và chuyên gia công nghệ với một trăn trở chung: Làm thế nào để việc học Y khoa tại Việt Nam trở nên bớt áp lực và hiệu quả hơn? Chúng tôi hiểu rằng khối lượng kiến thức khổng lồ và áp lực từ các kỳ thi nội trú, CCHN luôn là rào cản lớn.",
                'paragraph_2' => "Sứ mệnh của chúng tôi là nâng tầm giáo dục y khoa thông qua việc chuẩn hóa nội dung theo hướng cập nhật quốc tế (UpToDate, Harrison) nhưng vẫn sát với thực tế lâm sàng tại Việt Nam. {$app} không chỉ là một ngân hàng câu hỏi, mà là người bạn đồng hành thông minh trên con đường sự nghiệp của mỗi nhân viên y tế.",
                'tagline' => 'Học tập không ngừng - Tận tâm cống hiến',
                'image_url' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBradTgp2EYCoME1EiNEBjd7QdQ83DyDhBvMX1WKZ-BkYHohu6DwOX13sP8EQwEN4Wre-gOAev0jBO8f4CYqT6XG06iHQofiWg1zdQlcZgzk595ojV08v3jP7OXkKxYsPhy0ICsiLS8UZA5O1BC-gm-dHlsiEi6HpRCn-7w2hMXpDME982f0M9cIyBa0Cs2VUpic76Rw9dW206ickH6rqcOWPB6GyTRsSmK3p5FaCKGjvNhDseTbmncDA_uVTNF4iRyNNtilMc3ajCw',
                'image_alt' => 'Đội ngũ bác sĩ và chuyên gia giáo dục y khoa',
            ],
            'values' => [
                'heading' => 'Giá trị cốt lõi',
                'items' => [
                    ['title' => 'Chuẩn hóa nội dung', 'description' => 'Nội dung được thẩm định bởi hội đồng chuyên môn là các Tiến sĩ, Bác sĩ uy tín.'],
                    ['title' => 'Cá nhân hóa', 'description' => 'Thuật toán AI phân tích điểm mạnh yếu để gợi ý lộ trình học tập tối ưu.'],
                    ['title' => 'Đồng hành', 'description' => 'Cộng đồng học tập sôi nổi cùng sự hỗ trợ 24/7 từ đội ngũ học thuật.'],
                    ['title' => 'Minh bạch & Tin cậy', 'description' => 'Dữ liệu kết quả chính xác, cam kết bảo mật và hỗ trợ người dùng tối đa.'],
                ],
            ],
            'stats' => [
                'items' => [
                    ['value' => '12.450', 'label' => 'Câu hỏi bản quyền'],
                    ['value' => '38.000+', 'label' => 'Người học tin dùng'],
                    ['value' => '18', 'label' => 'Chuyên ngành Y khoa'],
                    ['value' => '96%', 'label' => 'Tỉ lệ hài lòng'],
                ],
            ],
            'experts' => [
                'heading' => 'Đội ngũ chuyên gia',
                'subtitle' => 'Hội tụ những chuyên gia đầu ngành trong lĩnh vực giáo dục Y khoa.',
                'items' => [
                    ['name' => 'TS.BS. Trần Văn Minh', 'role' => 'Cố vấn chuyên môn Nội khoa', 'image_url' => 'https://lh3.googleusercontent.com/aida/AP1WRLvDkCD578cYSin-_kyksoOYCeWLfQxoh0ZTWBZy5-_zt-SbD2tW9eoMaq_M6YUbfNlnow98krzw39xUwDoSEqzuuGWFyKqA11h-zFcHi4jWoqXPj-lOGj_aN04Fn-6_sXQO29_VVSkgWMZjidwzDkxnxyY89YwqIkGdvnlFoTiwCFh3oYGh5DlIOKGcNeEWdOKEfatN8nRFv7MCM3ZzEUTN2uFLmmnayHgpnZ6BfkW50mM9uu6QUiPMJBTg'],
                    ['name' => 'ThS.BS. Nguyễn Anh Tuấn', 'role' => 'Trưởng ban Nội dung Ngoại khoa', 'image_url' => 'https://lh3.googleusercontent.com/aida/AP1WRLuRxdaTKCUbtSR1qPPV5uACq8CGIzKijXHk6wE5sBFApFbN_-6hb5MS7DYkIKvBjAz0UngBX6FO3KMnbGHdnxeodWzYbzaB3nV5MawYvq3XEnSEr5vvm3j3pJ_-fNPelD_sgG5o7T0lg_ocm8OpUHyaMJ3Mi2Swz4bGLYNaI65fFRUnRV6sS1ss4KvVH_TnFbQc5r_wo9X7qSzOjOJM8fq4nxYMSGcX-FRJqNdY2VAKeM7wRfBlgC_iw9o'],
                    ['name' => 'BSCKII. Lê Thị Hồng', 'role' => 'Cố vấn Sản Nhi khoa', 'image_url' => 'https://lh3.googleusercontent.com/aida/AP1WRLtwYR3tTWOwSsruwTUSvSu9mwiYyaBzQQ-j2nNplJLV1M7jqlHf4RVl_P1_1d0AtDr_4dk049zwABVX9p2zAbA6thOd19fQ8cKI-sHw6gFgVicUIO2Gjz9XjWbdkTLiHOipK23p7z4ouSNNoNavmQbpP2avgZ9d_VRvPuX5d2VjJv6KXQsdS04ExugM4JcD9v8ONCi1LfBJGpnW-ht2d2djdgwc-20QDKw2_KhjmhMiVQc8oiqgrUnep7Y'],
                    ['name' => 'PGS.TS. Phạm Hoàn', 'role' => 'Cố vấn Giải phẫu - Mô phôi', 'image_url' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCw_DbNNSAw_zy8LSm11WqN43b-ZYHxpfo5m2pbBl3NPhiS4GGndTBFKpvhpMsuVlsn11GxahSI7WrDIxs0pMxJBqJcKilh_N_TzrOKld7ZX8s_ElKiz5FN4PnC_dj51e_SBb6W9DCOU0GyTxEWArhD3n-ogSbi407oUPzf4P9Tu9PRCf_ucpklPrSS6XLaAwS8ZAcLrh3FS2IcagNalWlEW2J8-HLNn8K2fH8HXSaPiVXUEAmSKjDnJeSSaJ3yE8KfK_ZABxwi4_PD'],
                    ['name' => 'ThS.BS. Mai Phương', 'role' => 'Giảng viên Anh văn Y khoa', 'image_url' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDzS3YyBkfMlTWkikdcWNZxS9Dxk-mFUXU7XdALcZCCxAP7SoPxlM-bKf2Bwamqt8vlT8OcWU_A9IGpNtFfxFqGP5qBSh3aazsa1oCFLPJYnt746a8NY7H9jTMRHZymPCQCFWn3qQFxWef34-25K_zNk9zGVm2w7VEaG-BIgvCES8c_x9Bn_cq2TzOGIZNJ0eUFDMNVYn3gYRShrkFhUft-pTqcxqFIJuVLckZ_5Hb1prBm0RB9UYn4eipEZNaxmESj9j5_AxJyWAQU'],
                    ['name' => 'BS. Đặng Quốc Huy', 'role' => 'Trưởng bộ phận Ngân hàng câu hỏi', 'image_url' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCU8u8aTDrRlZdatPcmDdLf_7WdbB0gxjCHm2nSka8mNqMRvawnV2QFaTnP9HfFWC4E5ndA49K6e7JSjnW_zIBhiO187MROFnGY2aC78SnRwv5Jms23buI0yDv76mainelos6eOxHvRKpOZL_EKwd2i3a4Lt7u34iXJfTv1sS__uQo3kqZ8rtd6h2m6YtxUSPQGcAR0JIRXV6Ss-qNjptyHDoYM65rU2nLvkl_4dLAH2X1R71Tje6gpNmo0Ja_ZodfYSP_BD2NPPzL_'],
                ],
            ],
            'partners' => [
                'label' => 'Đối tác đồng hành',
                'items' => ['ĐH Y HÀ NỘI', 'ĐH Y DƯỢC TP.HCM', 'BV BẠCH MAI', 'BV CHỢ RẪY', 'ĐH Y DƯỢC HUẾ'],
            ],
            'cta' => [
                'title' => "Cùng {$app} chinh phục kỳ thi",
                'subtitle' => 'Hàng ngàn sinh viên đã cải thiện điểm số chỉ sau 30 ngày luyện tập. Bắt đầu ngay hôm nay!',
                'primary_label' => 'Đăng ký miễn phí',
                'secondary_label' => 'Tìm hiểu thêm',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function contact(string $app): array
    {
        return [
            'intro' => [
                'title' => 'Liên hệ với chúng tôi',
                'text' => "{$app} luôn sẵn sàng lắng nghe và hỗ trợ bạn. Hãy liên hệ với chúng tôi qua các kênh dưới đây hoặc điền vào form bên cạnh.",
            ],
            'email' => 'hotro@medpro.vn',
            'hotline' => '1900 1234',
            'address' => 'Tầng 5, Toà nhà ABC, Hà Nội',
            'hours' => 'T2–T6, 8:00–17:30',
            'map' => [
                'image_url' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBItZ0hKQLUEejsds47JqQH3IuvKtmwXKTilR1D-Qqwz1djdkoCVKsGWLaH_F2YaD9ManNRQKLNYdULJ3GfPAjVXd6vbgUmuCI-VbHKKR5jW73y1hiYPY11p5ByC0idhXfrT0-jM5vXzEocTPYJL5m7JulRnGK_28B5DdcEgLAu7_ZJ1qqzo51BeWdJ4P5Xq05fKA_LCFpzd0RPEw-A9SMXMOp44mI7LXDrRODxaPI5m7QJlofjqcqe_lGb66nKBS4hXY85OWQZGENK',
                'image_alt' => 'Bản đồ văn phòng tại Hà Nội',
            ],
        ];
    }

    /**
     * @param  list<array{title: string, body: string}>  $sections
     * @return array<string, mixed>
     */
    private static function legalPage(string $intro, array $sections): array
    {
        return [
            'intro' => $intro,
            'sections' => $sections,
        ];
    }
}
