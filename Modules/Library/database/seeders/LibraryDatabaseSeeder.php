<?php

namespace Modules\Library\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Modules\Library\Models\Article;

class LibraryDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $articles = [
            [
                'type' => 'article',
                'slug' => 'viem-phoi-cong-dong',
                'title' => 'Viêm phổi cộng đồng',
                'summary' => 'Tổng quan về viêm phổi cộng đồng, dấu hiệu lâm sàng và chẩn đoán ban đầu.',
                'body' => '<h2>Định nghĩa</h2><p>Viêm phổi cộng đồng là nhiễm trùng nhu mô phổi xảy ra ngoài bệnh viện.</p><h2>Triệu chứng</h2><p>Sốt, ho, khó thở, đau ngực kiểu màng phổi.</p><h2>Chẩn đoán</h2><p>X quang ngực, bạch cầu tăng, SpO2 giảm.</p><h2>Điều trị</h2><p>Kháng sinh theo mức độ nặng và yếu tố nguy cơ.</p>',
                'is_free' => true,
            ],
            [
                'type' => 'disease',
                'slug' => 'suy-tim-sung-huyet',
                'title' => 'Suy tim sung huyết',
                'summary' => 'Bài bệnh học về suy tim, cơ chế sung huyết và hướng xử trí.',
                'body' => '<h2>Sinh lý bệnh</h2><p>Suy tim làm giảm cung lượng tim và tăng áp lực đổ đầy.</p><h2>Lâm sàng</h2><p>Khó thở khi gắng sức, phù ngoại biên, ran ẩm đáy phổi.</p><h2>Điều trị</h2><p>Lợi tiểu, ACEi/ARB, beta-blocker và kiểm soát dịch.</p>',
                'is_free' => true,
            ],
            [
                'type' => 'disease',
                'slug' => 'tang-huyet-ap-nguyen-phat',
                'title' => 'Tăng huyết áp nguyên phát',
                'summary' => 'Tổng quan chẩn đoán, mục tiêu huyết áp và điều trị tăng huyết áp.',
                'body' => '<h2>Chẩn đoán</h2><p>Đo huyết áp nhiều lần, loại trừ nguyên nhân thứ phát.</p><h2>Điều trị</h2><p>Giảm muối, vận động, ACEi, CCB, thiazide.</p><h2>Biến chứng</h2><p>Đột quỵ, bệnh thận mạn, phì đại thất trái.</p>',
                'is_free' => false,
            ],
            [
                'type' => 'drug',
                'slug' => 'amoxicillin',
                'title' => 'Amoxicillin',
                'summary' => 'Kháng sinh beta-lactam thường dùng trong nhiễm khuẩn hô hấp trên.',
                'body' => '<h2>Cơ chế</h2><p>Ức chế tổng hợp thành tế bào vi khuẩn.</p><h2>Chỉ định</h2><p>Viêm tai giữa, viêm xoang, viêm phổi cộng đồng nhẹ.</p><h2>Tác dụng phụ</h2><p>Phát ban, tiêu chảy, dị ứng.</p>',
                'is_free' => true,
            ],
            [
                'type' => 'drug',
                'slug' => 'metformin',
                'title' => 'Metformin',
                'summary' => 'Thuốc hàng đầu trong đái tháo đường type 2.',
                'body' => '<h2>Cơ chế</h2><p>Giảm tân tạo glucose ở gan và cải thiện nhạy cảm insulin.</p><h2>Chỉ định</h2><p>Đái tháo đường type 2, hội chứng chuyển hóa.</p><h2>Lưu ý</h2><p>Theo dõi chức năng thận và nguy cơ toan lactic.</p>',
                'is_free' => true,
            ],
            [
                'type' => 'procedure',
                'slug' => 'choc-doi-mang-phoi',
                'title' => 'Chọc dò màng phổi',
                'summary' => 'Các bước cơ bản của thủ thuật chọc dò màng phổi.',
                'body' => '<h2>Chỉ định</h2><p>Tràn dịch màng phổi chẩn đoán hoặc điều trị.</p><h2>Chuẩn bị</h2><p>Giải thích thủ thuật, siêu âm định vị, vô khuẩn.</p><h2>Biến chứng</h2><p>Tràn khí màng phổi, chảy máu, nhiễm trùng.</p>',
                'is_free' => false,
            ],
            [
                'type' => 'procedure',
                'slug' => 'dat-noi-khi-quan',
                'title' => 'Đặt nội khí quản',
                'summary' => 'Tổng quan quy trình đặt nội khí quản cấp cứu.',
                'body' => '<h2>Chỉ định</h2><p>Suy hô hấp, bảo vệ đường thở, ngưng thở.</p><h2>Các bước</h2><p>Chuẩn bị dụng cụ, tiền oxy hóa, soi thanh quản, xác nhận vị trí.</p><h2>Theo dõi</h2><p>EtCO2, SpO2, huyết áp và cố định ống.</p>',
                'is_free' => false,
            ],
            [
                'type' => 'article',
                'slug' => 'hoc-hut-thuoc-va-copd',
                'title' => 'Hút thuốc và bệnh phổi tắc nghẽn mạn tính',
                'summary' => 'Liên hệ giữa hút thuốc lá, COPD và tư vấn cai thuốc.',
                'body' => '<h2>Yếu tố nguy cơ</h2><p>Hút thuốc lá là nguyên nhân hàng đầu của COPD.</p><h2>Triệu chứng</h2><p>Ho khạc đờm mạn tính, khó thở tăng dần.</p><h2>Điều trị</h2><p>Cai thuốc, giãn phế quản, phục hồi chức năng phổi.</p>',
                'is_free' => true,
            ],
            [
                'type' => 'article',
                'slug' => 'sot-xuat-huyet-dau-hieu-canh-bao',
                'title' => 'Sốt xuất huyết: dấu hiệu cảnh báo',
                'summary' => 'Bài nền tảng giúp nhận diện dấu hiệu cảnh báo trong sốt xuất huyết.',
                'body' => '<h2>Dấu hiệu cảnh báo</h2><p>Đau bụng, nôn nhiều, niêm mạc chảy máu, lừ đừ.</p><h2>Theo dõi</h2><p>Hematocrit, tiểu cầu, dịch vào ra.</p><h2>Xử trí</h2><p>Bù dịch hợp lý và theo dõi sát trong giai đoạn nguy hiểm.</p>',
                'is_free' => true,
            ],
            [
                'type' => 'article',
                'slug' => 'dau-nguc-cap-tiep-can',
                'title' => 'Đau ngực cấp: cách tiếp cận',
                'summary' => 'Bài ôn tập về tiếp cận đau ngực cấp trong cấp cứu.',
                'body' => '<h2>Tiếp cận</h2><p>Loại trừ hội chứng vành cấp, thuyên tắc phổi, bóc tách động mạch chủ.</p><h2>Xét nghiệm</h2><p>ECG, troponin, X quang ngực, khí máu khi cần.</p><h2>Nguyên tắc</h2><p>Ưu tiên chẩn đoán nguy hiểm trước.</p>',
                'is_free' => false,
            ],
        ];

        foreach ($articles as $article) {
            Article::query()->updateOrCreate(
                ['slug' => $article['slug']],
                [
                    ...$article,
                    'is_published' => true,
                    'published_at' => $now,
                ],
            );
        }
    }
}
