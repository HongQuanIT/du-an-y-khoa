<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionOption;

/**
 * Idempotent sample bank: 50 published MCQs linked across curriculum lessons.
 */
final class SampleQuestionBankSeeder extends Seeder
{
    public const TARGET = 50;

    public function run(): void
    {
        $this->call(MedicalKnowledgeTaxonomySeeder::class);

        Question::withoutSyncingToSearch(function (): void {
            $lessons = Lesson::query()->orderBy('sort_order')->orderBy('id')->get();
            if ($lessons->isEmpty()) {
                $this->command?->warn('SampleQuestionBankSeeder: không có bài học — bỏ qua.');

                return;
            }

            foreach ($this->catalog() as $index => $row) {
                $lesson = $lessons[$index % $lessons->count()];
                $code = sprintf('QBANK-SAMPLE-%03d', $index + 1);

                $question = Question::withTrashed()->where('code', $code)->first();
                $payload = [
                    'stem' => $row['stem'],
                    'explanation' => $row['explanation'],
                    'difficulty' => $row['difficulty'],
                    'status' => QuestionStatus::Published,
                    'is_free' => $index % 3 === 0,
                    'exam_flag' => $index % 5 === 0,
                    'key_info' => [$row['hint']],
                    'attending_tip' => $row['tip'],
                ];

                if ($question !== null) {
                    if ($question->trashed()) {
                        $question->restore();
                    }
                    $question->fill($payload)->save();
                } else {
                    $question = new Question($payload);
                    $question->code = $code;
                    $question->version = 1;
                    $question->save();
                }

                $this->syncOptions($question, $row['options'], $row['correct']);
                $question->lessons()->sync([(int) $lesson->getKey()]);
            }
        });
    }

    /**
     * @param  list<string>  $options
     */
    private function syncOptions(Question $question, array $options, int $correct): void
    {
        QuestionOption::query()->where('question_id', $question->getKey())->delete();

        foreach ($options as $i => $content) {
            QuestionOption::query()->create([
                'question_id' => $question->getKey(),
                'label' => chr(ord('A') + $i),
                'content' => $content,
                'is_correct' => $i === $correct,
                'explanation' => $i === $correct ? $question->explanation : null,
                'order' => $i + 1,
            ]);
        }
    }

    /**
     * @return list<array{
     *   stem: string,
     *   explanation: string,
     *   hint: string,
     *   tip: string,
     *   difficulty: Difficulty,
     *   options: list<string>,
     *   correct: int
     * }>
     */
    private function catalog(): array
    {
        $items = [
            [
                'stem' => '<p>Nam 62 tuổi đau ngực sau xương ức kéo dài 45 phút, lan tay trái, vã mồ hôi. ECG: ST chênh lên V2–V4. Chẩn đoán ưu tiên?</p>',
                'explanation' => '<p>Đau ngực kiểu mạch vành + ST chênh lên vùng trước phù hợp STEMI.</p>',
                'hint' => 'Kết hợp triệu chứng và biến đổi ST.',
                'tip' => '<p>STEMI cần tái tưới máu khẩn.</p>',
                'difficulty' => Difficulty::Hard,
                'options' => ['Đau thắt ngực ổn định', 'STEMI', 'Viêm màng ngoài tim', 'GERD'],
                'correct' => 1,
            ],
            [
                'stem' => '<p>Bệnh nhân có tiền sử CAD, đau ngực khi gắng sức giảm khi nghỉ. ECG lúc nghỉ bình thường. Chẩn đoán phù hợp nhất?</p>',
                'explanation' => '<p>Đau thắt ngực ổn định: khởi phát khi gắng sức, giảm khi nghỉ.</p>',
                'hint' => 'Mối liên quan với gắng sức.',
                'tip' => '<p>Đánh giá nguy cơ và tối ưu hóa điều trị nội khoa.</p>',
                'difficulty' => Difficulty::Easy,
                'options' => ['STEMI', 'Đau thắt ngực ổn định', 'Thuyên tắc phổi', 'Bóc tách động mạch chủ'],
                'correct' => 1,
            ],
            [
                'stem' => '<p>Đau ngực khi nghỉ, ST chênh xuống thoáng qua, troponin âm tính. Tình trạng nào phù hợp nhất?</p>',
                'explanation' => '<p>Đau khi nghỉ + thay đổi ST thoáng qua + troponin âm tính gợi ý hội chứng vành cấp không ST chênh lên / đau không ổn định.</p>',
                'hint' => 'Troponin âm tính giúp loại trừ nhồi máu.',
                'tip' => '<p>Phân tầng nguy cơ sớm.</p>',
                'difficulty' => Difficulty::Medium,
                'options' => ['STEMI', 'Hội chứng vành cấp không ST chênh lên', 'Viêm phổi', 'Loét dạ dày'],
                'correct' => 1,
            ],
            [
                'stem' => '<p>Suy tim phân suất tống máu giảm: triệu chứng nào thường gặp nhất khi gắng sức?</p>',
                'explanation' => '<p>Khó thở khi gắng sức là triệu chứng điển hình của suy tim.</p>',
                'hint' => 'Triệu chứng sung huyết phổi.',
                'tip' => '<p>Đánh giá NYHA và tối ưu hóa điều trị nền.</p>',
                'difficulty' => Difficulty::Easy,
                'options' => ['Khó thở khi gắng sức', 'Ngứa toàn thân', 'Đau khớp cổ tay', 'Ù tai'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Bệnh nhân hồi hộp, mạch không đều, ECG thấy sóng P không đều với khoảng RR không đều. Chẩn đoán?</p>',
                'explanation' => '<p>Rung nhĩ: không có sóng P rõ, RR không đều.</p>',
                'hint' => 'Nhịp không đều + mất sóng P.',
                'tip' => '<p>Cân nhắc kiểm soát tần số/nhịp và chống đông.</p>',
                'difficulty' => Difficulty::Medium,
                'options' => ['Ngoại tâm thu thất', 'Rung nhĩ', 'Block AV độ 3', 'Nhịp xoang'],
                'correct' => 1,
            ],
            [
                'stem' => '<p>Sốt, ho đàm, ran ẩm khu trú, X-quang có thâm nhiễm thùy dưới. Chẩn đoán?</p>',
                'explanation' => '<p>Viêm phổi cộng đồng: sốt + đàm + thâm nhiễm khu trú.</p>',
                'hint' => 'Thâm nhiễm thùy trên X-quang.',
                'tip' => '<p>Chọn kháng sinh theo hướng dẫn và mức độ nặng.</p>',
                'difficulty' => Difficulty::Easy,
                'options' => ['Viêm phổi cộng đồng', 'Hen phế quản', 'Tràn khí màng phổi', 'Lao hạch'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Hen phế quản cấp: dấu hiệu nào gợi ý mức độ nặng?</p>',
                'explanation' => '<p>Nói từng từ, SpO2 thấp, rút lõm / dùng cơ hô hấp phụ là dấu hiệu nặng.</p>',
                'hint' => 'Đánh giá mức độ khó thở và SpO2.',
                'tip' => '<p>Dùng beta-agonist khí dung và corticoid sớm.</p>',
                'difficulty' => Difficulty::Medium,
                'options' => ['Chỉ ho khan nhẹ', 'Nói được câu dài dễ dàng', 'SpO2 88% và khó nói', 'Ngứa mũi đơn thuần'],
                'correct' => 2,
            ],
            [
                'stem' => '<p>Bệnh nhân COPD, khó thở mạn, FEV1/FVC < 0.7 sau giãn phế quản. Kết luận nào đúng?</p>',
                'explanation' => '<p>FEV1/FVC < 0.7 sau giãn phế quản khẳng định tắc nghẽn cố định của COPD.</p>',
                'hint' => 'Tiêu chuẩn hô hấp ký.',
                'tip' => '<p>Phân nhóm GOLD để chọn điều trị duy trì.</p>',
                'difficulty' => Difficulty::Medium,
                'options' => ['Hen thuần túy', 'COPD', 'Tràn dịch màng phổi', 'Viêm phổi kẽ cấp'],
                'correct' => 1,
            ],
            [
                'stem' => '<p>Đột ngột khó thở, đau ngực kiểu màng phổi, SpO2 giảm sau chuyến bay dài. Chẩn đoán ưu tiên?</p>',
                'explanation' => '<p>Thuyên tắc phổi: khởi phát đột ngột sau bất động kéo dài.</p>',
                'hint' => 'Yếu tố nguy cơ huyết khối tĩnh mạch.',
                'tip' => '<p>Đánh giá Wells / Geneva và D-dimer hoặc CTPA.</p>',
                'difficulty' => Difficulty::Hard,
                'options' => ['Hen cấp', 'Thuyên tắc phổi', 'Loét dạ dày', 'Gout cấp'],
                'correct' => 1,
            ],
            [
                'stem' => '<p>Đau thượng vị liên quan bữa ăn, giảm khi dùng PPI, Helicobacter pylori (+). Chẩn đoán phù hợp?</p>',
                'explanation' => '<p>Loét dạ dày – tá tràng liên quan H. pylori thường đáp ứng PPI + diệt khuẩn.</p>',
                'hint' => 'Liên quan bữa ăn và H. pylori.',
                'tip' => '<p>Xác nhận diệt khuẩn sau điều trị khi có chỉ định.</p>',
                'difficulty' => Difficulty::Easy,
                'options' => ['Viêm tụy cấp', 'Loét dạ dày – tá tràng', 'Viêm ruột thừa', 'Sỏi mật không triệu chứng'],
                'correct' => 1,
            ],
            [
                'stem' => '<p>Đau thượng vị dữ dội lan sau lưng, lipase tăng cao sau uống rượu. Chẩn đoán?</p>',
                'explanation' => '<p>Viêm tụy cấp: đau lan lưng + lipase tăng + yếu tố rượu.</p>',
                'hint' => 'Men tụy và hướng lan đau.',
                'tip' => '<p>Bù dịch sớm, giảm đau, tìm nguyên nhân.</p>',
                'difficulty' => Difficulty::Medium,
                'options' => ['Viêm tụy cấp', 'Viêm dạ dày đơn thuần', 'Nhồi máu cơ tim sau dưới', 'Cơn gout'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Bệnh nhân xơ gan: dấu hiệu nào gợi ý tăng áp lực tĩnh mạch cửa?</p>',
                'explanation' => '<p>Giãn tĩnh mạch thực quản, lách to, cổ chướng là biểu hiện tăng áp cửa.</p>',
                'hint' => 'Biểu hiện ngoại vi của tăng áp cửa.',
                'tip' => '<p>Sàng lọc giãn tĩnh mạch thực quản.</p>',
                'difficulty' => Difficulty::Medium,
                'options' => ['Giãn tĩnh mạch thực quản', 'Tăng huyết áp tâm thu đơn độc', 'Hạ kali máu đơn độc', 'Đau khớp gối'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Đau hố chậu phải di chuyển từ quanh rốn, sốt nhẹ, bạch cầu tăng. Chẩn đoán ưu tiên?</p>',
                'explanation' => '<p>Viêm ruột thừa: đau di chuyển + sốt + bạch cầu tăng.</p>',
                'hint' => 'Đặc điểm di chuyển của đau.',
                'tip' => '<p>Khám bụng và hình ảnh học khi nghi ngờ.</p>',
                'difficulty' => Difficulty::Easy,
                'options' => ['Viêm ruột thừa', 'Sỏi thận trái', 'Hen cấp', 'Thiếu máu thiếu sắt'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Yếu nửa người phải đột ngột, méo miệng, nói khó trong lúc đang làm việc. Ưu tiên chẩn đoán?</p>',
                'explanation' => '<p>Khởi phát đột ngột thiếu sót thần kinh khu trú là đột quỵ cho đến khi chứng minh ngược lại.</p>',
                'hint' => 'Thời gian khởi phát và dấu khu trú.',
                'tip' => '<p>Kích hoạt quy trình đột quỵ cấp.</p>',
                'difficulty' => Difficulty::Hard,
                'options' => ['Migraine không triệu chứng', 'Đột quỵ', 'Viêm dạ dày', 'Loãng xương'],
                'correct' => 1,
            ],
            [
                'stem' => '<p>Sốt, đau đầu dữ dội, cứng gáy, rối loạn tri giác. Chẩn đoán khẩn cấp?</p>',
                'explanation' => '<p>Bộ ba sốt – cứng gáy – rối loạn tri giác gợi ý viêm màng não.</p>',
                'hint' => 'Dấu màng não.',
                'tip' => '<p>Kháng sinh sớm sau lấy mẫu khi có chỉ định.</p>',
                'difficulty' => Difficulty::Hard,
                'options' => ['Viêm màng não', 'Cảm lạnh thường', 'Loét dạ dày', 'Gout'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Cơn co giật toàn thể lần đầu ở người lớn, sau cơn có lơ mơ. Bước xử trí ban đầu phù hợp?</p>',
                'explanation' => '<p>Bảo vệ đường thở, tránh chấn thương, theo dõi; benzodiazepine nếu cơn kéo dài.</p>',
                'hint' => 'An toàn và đường thở trước.',
                'tip' => '<p>Tìm nguyên nhân kích thích và chỉ định hình ảnh/EEG.</p>',
                'difficulty' => Difficulty::Medium,
                'options' => ['Ép ngực ngay lập tức', 'Bảo vệ đường thở và theo dõi', 'Cho ăn ngay', 'Xuất viện ngay không đánh giá'],
                'correct' => 1,
            ],
            [
                'stem' => '<p>Đau đầu một bên từng cơn kèm buồn nôn, sợ ánh sáng, không yếu liệt. Chẩn đoán phù hợp?</p>',
                'explanation' => '<p>Migraine điển hình: đau nửa đầu + buồn nôn / sợ ánh sáng, không thiếu sót thần kinh cố định.</p>',
                'hint' => 'Triệu chứng kèm theo cảm giác.',
                'tip' => '<p>Phân biệt với đột quỵ khi có dấu khu trú mới.</p>',
                'difficulty' => Difficulty::Easy,
                'options' => ['Đột quỵ xuất huyết', 'Migraine', 'Viêm màng não mủ', 'Suy thận cấp'],
                'correct' => 1,
            ],
            [
                'stem' => '<p>Khát nhiều, tiểu nhiều, HbA1c 9.2%. Chẩn đoán nền phù hợp nhất?</p>',
                'explanation' => '<p>Tam chứng kinh điển và HbA1c cao phù hợp đái tháo đường.</p>',
                'hint' => 'Triệu chứng kinh điển + HbA1c.',
                'tip' => '<p>Giáo dục và khởi trị theo guideline.</p>',
                'difficulty' => Difficulty::Easy,
                'options' => ['Đái tháo đường', 'Cường giáp đơn thuần', 'Gout', 'COPD'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Người bệnh đái tháo đường: khát nhiều, tiểu nhiều, hơi thở mùi trái cây, ceton (+). Chẩn đoán cấp cứu?</p>',
                'explanation' => '<p>Nhiễm toan ceton do đái tháo đường (DKA).</p>',
                'hint' => 'Ceton và hơi thở mùi trái cây.',
                'tip' => '<p>Bù dịch, insulin, theo dõi điện giải.</p>',
                'difficulty' => Difficulty::Hard,
                'options' => ['Hạ đường huyết', 'DKA', 'Cơn gout cấp', 'Suy giáp'],
                'correct' => 1,
            ],
            [
                'stem' => '<p>Sút cân, hồi hộp, run tay, TSH thấp, FT4 cao. Chẩn đoán?</p>',
                'explanation' => '<p>Cường giáp: TSH thấp kèm FT4 cao và triệu chứng cường chuyển hóa.</p>',
                'hint' => 'Trục TSH–FT4.',
                'tip' => '<p>Tìm nguyên nhân (Basedow, nhân độc…).</p>',
                'difficulty' => Difficulty::Medium,
                'options' => ['Suy giáp', 'Cường giáp', 'Suy thượng thận', 'Thiếu máu thiếu sắt'],
                'correct' => 1,
            ],
            [
                'stem' => '<p>Mệt, hạ huyết áp, tăng kali, da tăng sắc tố. Nghi ngờ bệnh lý nào?</p>',
                'explanation' => '<p>Suy thượng thận nguyên phát: hạ HA, tăng K+, tăng sắc tố.</p>',
                'hint' => 'Tăng sắc tố + rối loạn điện giải.',
                'tip' => '<p>Không trì hoãn corticoid khi nghi ngờ cơn Addison.</p>',
                'difficulty' => Difficulty::Hard,
                'options' => ['Cường aldosterone', 'Suy thượng thận', 'Cường giáp', 'Gout'],
                'correct' => 1,
            ],
            [
                'stem' => '<p>Tăng creatinin cấp sau mất dịch nặng, nước tiểu cô đặc. Cơ chế suy thận nào phù hợp?</p>',
                'explanation' => '<p>Suy thận trước thận do giảm tưới máu.</p>',
                'hint' => 'Bối cảnh giảm thể tích.',
                'tip' => '<p>Bù dịch và theo dõi đáp ứng creatinin.</p>',
                'difficulty' => Difficulty::Medium,
                'options' => ['Suy thận trước thận', 'Viêm cầu thận cấp điển hình', 'Tắc nghẽn niệu quản hai bên chắc chắn', 'Viêm bể thận mạn'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Sốt, đau hông lưng, tiểu đục, gõ hông (+) . Chẩn đoán ưu tiên?</p>',
                'explanation' => '<p>Viêm bể thận cấp: sốt + đau hông + triệu chứng tiết niệu.</p>',
                'hint' => 'Dấu hiệu tại chỗ và toàn thân.',
                'tip' => '<p>Cấy nước tiểu trước kháng sinh nếu có thể.</p>',
                'difficulty' => Difficulty::Easy,
                'options' => ['Viêm bể thận', 'Migraine', 'Hen cấp', 'Loét dạ dày'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Đau hông lưng từng cơn dữ dội lan xuống bẹn, tiểu máu vi thể. Chẩn đoán?</p>',
                'explanation' => '<p>Sỏi tiết niệu: đau quặn thận + tiểu máu.</p>',
                'hint' => 'Tính chất đau quặn và hướng lan.',
                'tip' => '<p>Giảm đau, đánh giá kích thước sỏi và biến chứng.</p>',
                'difficulty' => Difficulty::Medium,
                'options' => ['Viêm ruột thừa trái', 'Sỏi tiết niệu', 'Nhồi máu cơ tim', 'COPD'],
                'correct' => 1,
            ],
            [
                'stem' => '<p>Nam trẻ, đái máu cuối dòng, đau trên xương mu. Hướng chẩn đoán tiết niệu nào hợp lý?</p>',
                'explanation' => '<p>Đái máu cuối dòng gợi ý nguồn gốc đường tiết niệu dưới.</p>',
                'hint' => 'Thời điểm máu trong dòng tiểu.',
                'tip' => '<p>Khám và xét nghiệm nước tiểu định hướng vị trí chảy máu.</p>',
                'difficulty' => Difficulty::Medium,
                'options' => ['Chảy máu đường tiết niệu dưới', 'Xuất huyết tiêu hóa trên chắc chắn', 'Hemoptysis', 'Chảy máu nội sọ'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Mệt, da xanh, MCV thấp, ferritin thấp. Chẩn đoán thiếu máu nào?</p>',
                'explanation' => '<p>Thiếu máu thiếu sắt: hồng cầu nhỏ, nhược sắc, ferritin thấp.</p>',
                'hint' => 'MCV và ferritin.',
                'tip' => '<p>Tìm nguyên nhân mất máu mạn.</p>',
                'difficulty' => Difficulty::Easy,
                'options' => ['Thiếu máu thiếu sắt', 'Thiếu B12', 'Thiếu máu huyết tán cấp', 'Đa hồng cầu'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Bệnh nhân dùng warfarin, INR rất cao kèm chảy máu nướu. Hướng xử trí ưu tiên?</p>',
                'explanation' => '<p>Chảy máu do kháng vitamin K: đảo ngược theo mức độ chảy máu và INR.</p>',
                'hint' => 'Cân bằng chảy máu và đảo ngược đông máu.',
                'tip' => '<p>Theo protocol đảo ngược kháng đông.</p>',
                'difficulty' => Difficulty::Hard,
                'options' => ['Tăng liều warfarin', 'Đảo ngược kháng đông theo protocol', 'Truyền sắt ngay', 'Ngưng theo dõi'],
                'correct' => 1,
            ],
            [
                'stem' => '<p>Sốt, xuất huyết, bạch cầu rất cao kèm blast trên phết máu. Nghi ngờ?</p>',
                'explanation' => '<p>Bạch cầu cấp: blast + biểu hiện suy tủy / tăng sinh cấp.</p>',
                'hint' => 'Blast trên máu ngoại vi.',
                'tip' => '<p>Chuyển huyết học khẩn để khẳng định và điều trị.</p>',
                'difficulty' => Difficulty::Hard,
                'options' => ['Thiếu máu thiếu sắt', 'Bạch cầu cấp', 'Gout mạn', 'Hen ổn định'],
                'correct' => 1,
            ],
            [
                'stem' => '<p>Đau khớp ngón chân cái đột ngột, sưng đỏ, acid uric cao. Chẩn đoán?</p>',
                'explanation' => '<p>Gout cấp điển hình tại khớp bàn ngón chân cái.</p>',
                'hint' => 'Vị trí khớp và acid uric.',
                'tip' => '<p>Điều trị viêm cấp rồi dự phòng về sau.</p>',
                'difficulty' => Difficulty::Easy,
                'options' => ['Gout cấp', 'Viêm khớp dạng thấp sớm chắc chắn', 'Gãy xương', 'Viêm mô tế bào bàn chân chắc chắn'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Cứng khớp buổi sáng > 1 giờ, sưng khớp nhỏ đối xứng hai bàn tay. Gợi ý bệnh nào?</p>',
                'explanation' => '<p>Viêm khớp dạng thấp: cứng buổi sáng kéo dài và tổn thương đối xứng.</p>',
                'hint' => 'Thời gian cứng khớp và tính đối xứng.',
                'tip' => '<p>Chẩn đoán sớm để bắt đầu DMARD.</p>',
                'difficulty' => Difficulty::Medium,
                'options' => ['Thoái hóa khớp đơn thuần', 'Viêm khớp dạng thấp', 'Gout monoarticular', 'Loãng xương không viêm'],
                'correct' => 1,
            ],
            [
                'stem' => '<p>Nữ sau mãn kinh, gãy cổ xương đùi sau chấn thương nhẹ. Bệnh nền nào cần nghĩ tới?</p>',
                'explanation' => '<p>Loãng xương làm tăng nguy cơ gãy xương mong manh.</p>',
                'hint' => 'Gãy xương năng lượng thấp.',
                'tip' => '<p>Đo mật độ xương và điều trị chống hủy xương khi chỉ định.</p>',
                'difficulty' => Difficulty::Easy,
                'options' => ['Loãng xương', 'Gout', 'Hen', 'Cường giáp chắc chắn'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Trong STEMI trước, chuyển đạo nào thường thấy ST chênh lên?</p>',
                'explanation' => '<p>STEMI vùng trước thường biểu hiện ở V1–V4.</p>',
                'hint' => 'Liên hệ vùng cơ tim và chuyển đạo.',
                'tip' => '<p>Đừng quên đánh giá chuyển đạo đối diện.</p>',
                'difficulty' => Difficulty::Medium,
                'options' => ['V1–V4', 'II, III, aVF đơn độc luôn', 'aVR luôn đủ để chẩn đoán', 'Không cần ECG'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Troponin tăng sau đau ngực 6 giờ, không ST chênh lên. Tình trạng nào phù hợp?</p>',
                'explanation' => '<p>NSTEMI: tổn thương cơ tim (troponin) không kèm ST chênh lên kéo dài.</p>',
                'hint' => 'Marker tổn thương cơ tim.',
                'tip' => '<p>Phân tầng nguy cơ để quyết định chiến lược xâm lấn.</p>',
                'difficulty' => Difficulty::Medium,
                'options' => ['Đau thắt ngực ổn định', 'NSTEMI', 'Hen cấp', 'Gout'],
                'correct' => 1,
            ],
            [
                'stem' => '<p>Bệnh nhân suy tim: thuốc nào thuộc nhóm nền cải thiện tiên lượng (HFrEF)?</p>',
                'explanation' => '<p>Ức chế men chuyển / ARNI, beta-blocker, MRA, SGLT2i là nền tảng HFrEF.</p>',
                'hint' => 'Thuốc cải thiện sống còn.',
                'tip' => '<p>Tối ưu hóa liều theo dung nạp.</p>',
                'difficulty' => Difficulty::Hard,
                'options' => ['Chỉ dùng lợi tiểu quai dài hạn là đủ', 'ACEI/ARNI + beta-blocker trong nền tảng', 'Kháng sinh dự phòng hàng ngày', 'Vitamin C liều cao'],
                'correct' => 1,
            ],
            [
                'stem' => '<p>Viêm phổi nặng: tiêu chí nào gợi ý cần nhập viện / chăm sóc sát?</p>',
                'explanation' => '<p>Hạ huyết áp, SpO2 thấp, rối loạn tri giác là dấu hiệu nặng.</p>',
                'hint' => 'Dấu hiệu sinh tồn và oxy hóa.',
                'tip' => '<p>Dùng CURB-65 / PSI hỗ trợ quyết định.</p>',
                'difficulty' => Difficulty::Medium,
                'options' => ['Chỉ sốt nhẹ, SpO2 98%', 'Hạ HA và SpO2 86%', 'Ho khan không sốt', 'X-quang bình thường'],
                'correct' => 1,
            ],
            [
                'stem' => '<p>Cơn hen: thuốc cấp cứu đường hít đầu tay là gì?</p>',
                'explanation' => '<p>Short-acting beta-agonist (SABA) là thuốc cắt cơn đầu tay.</p>',
                'hint' => 'Giãn phế quản tác dụng ngắn.',
                'tip' => '<p>Kết hợp corticoid toàn thân khi cơn vừa–nặng.</p>',
                'difficulty' => Difficulty::Easy,
                'options' => ['SABA khí dung/xịt', 'Chỉ kháng sinh', 'Chỉ lợi tiểu', 'Warfarin'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Thuyên tắc phổi: xét nghiệm nào thường dùng để loại trừ khi xác suất thấp?</p>',
                'explanation' => '<p>D-dimer âm tính giúp loại trừ PE ở xác suất thấp.</p>',
                'hint' => 'Giá trị loại trừ của D-dimer.',
                'tip' => '<p>Không dùng D-dimer đơn độc khi xác suất cao.</p>',
                'difficulty' => Difficulty::Medium,
                'options' => ['D-dimer', 'HbA1c', 'Acid uric', 'TSH'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Xuất huyết tiêu hóa trên nghi do loét: bước ổn định ban đầu quan trọng?</p>',
                'explanation' => '<p>ABC, bù dịch/máu theo chỉ định, PPI, hội chẩn nội soi.</p>',
                'hint' => 'Ổn định huyết động trước.',
                'tip' => '<p>Nội soi trong khung thời gian phù hợp mức độ nặng.</p>',
                'difficulty' => Difficulty::Hard,
                'options' => ['Ổn định tuần hoàn và hội chẩn nội soi', 'Cho về nhà ngay', 'Chỉ dùng NSAID thêm', 'Không cần theo dõi Hb'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Viêm tụy cấp: tiêu chuẩn chẩn đoán thường cần bao nhiêu trong 3 yếu tố kinh điển?</p>',
                'explanation' => '<p>Cần ≥2/3: đau điển hình, men tụy ≥3 lần, hình ảnh phù hợp.</p>',
                'hint' => 'Tiêu chuẩn Atlanta.',
                'tip' => '<p>Không trì hoãn điều trị hỗ trợ chờ hình ảnh nếu đã đủ tiêu chuẩn.</p>',
                'difficulty' => Difficulty::Medium,
                'options' => ['≥2/3 tiêu chuẩn', 'Bắt buộc đủ cả 3 luôn', 'Chỉ cần sốt', 'Chỉ cần đau nhẹ'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Xơ gan mất bù: biến chứng nào sau đây là cấp cứu thường gặp?</p>',
                'explanation' => '<p>Xuất huyết do vỡ giãn tĩnh mạch thực quản là cấp cứu thường gặp.</p>',
                'hint' => 'Biến chứng tăng áp cửa.',
                'tip' => '<p>Ổn định và nội soi cầm máu khi nghi vỡ giãn tĩnh mạch.</p>',
                'difficulty' => Difficulty::Hard,
                'options' => ['Vỡ giãn tĩnh mạch thực quản', 'Gãy xương cổ tay nhẹ', 'Viêm họng virus', 'Hạ acid uric'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Đột quỵ thiếu máu: cửa sổ điều trị tiêu huyết khối đường tĩnh mạch thường được nhắc tới trong bao lâu từ khởi phát?</p>',
                'explanation' => '<p>Nhiều hướng dẫn dùng cửa sổ khoảng 4.5 giờ cho tPA chọn lọc.</p>',
                'hint' => 'Thời gian vàng.',
                'tip' => '<p>Xác định giờ khởi phát chính xác là then chốt.</p>',
                'difficulty' => Difficulty::Hard,
                'options' => ['Khoảng 4.5 giờ (chọn lọc)', 'Sau 72 giờ mới bắt đầu', 'Không liên quan thời gian', 'Chỉ sau 1 tuần'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Viêm màng não mủ nghi ngờ: xét nghiệm then chốt để khẳng định?</p>',
                'explanation' => '<p>Chọc dịch não tủy (khi không chống chỉ định) là then chốt.</p>',
                'hint' => 'Dịch não tủy.',
                'tip' => '<p>Không trì hoãn kháng sinh nếu trì hoãn lấy mẫu nguy hiểm.</p>',
                'difficulty' => Difficulty::Medium,
                'options' => ['Phân tích dịch não tủy', 'Chỉ X-quang ngực', 'Chỉ đo đường huyết mao mạch', 'Chỉ ECG'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Đái tháo đường type 2 ổn định: mục tiêu HbA1c cá thể hóa thường quanh mức nào với nhiều bệnh nhân?</p>',
                'explanation' => '<p>Nhiều hướng dẫn đặt mục tiêu quanh <7% tùy cá thể.</p>',
                'hint' => 'Cá thể hóa theo nguy cơ hạ đường huyết.',
                'tip' => '<p>Nới lỏng mục tiêu ở người già / nhiều bệnh đồng mắc.</p>',
                'difficulty' => Difficulty::Easy,
                'options' => ['Quanh <7% (cá thể hóa)', 'Luôn <4%', 'Không cần theo dõi HbA1c', 'Chỉ theo dõi khi có DKA'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Cường giáp: thuốc nào thường dùng để kiểm soát triệu chứng hồi hộp nhanh?</p>',
                'explanation' => '<p>Beta-blocker giúp kiểm soát triệu chứng cường adrenergic.</p>',
                'hint' => 'Kiểm soát triệu chứng trước khi hormone bình thường hóa.',
                'tip' => '<p>Điều trị nguyên nhân song song (thuốc kháng giáp, iode…).</p>',
                'difficulty' => Difficulty::Medium,
                'options' => ['Beta-blocker', 'Insulin thường quy', 'Warfarin mặc định', 'Salbutamol đơn độc'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Suy thận cấp: kali máu 6.8 mmol/L có biến đổi ECG. Ưu tiên hàng đầu?</p>',
                'explanation' => '<p>Ổn định màng tế bào bằng calcium và hạ K+ khẩn khi có thay đổi ECG.</p>',
                'hint' => 'Tăng K+ có nguy cơ loạn nhịp.',
                'tip' => '<p>Song song tìm và điều trị nguyên nhân.</p>',
                'difficulty' => Difficulty::Hard,
                'options' => ['Xử trí tăng kali cấp có biến đổi ECG', 'Chờ tái khám tuần sau', 'Chỉ bổ sung kali thêm', 'Ngưng theo dõi tim'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Viêm bể thận không biến chứng ở người lớn: nguyên tắc điều trị chính?</p>',
                'explanation' => '<p>Kháng sinh phù hợp phổ vi khuẩn đường tiết niệu và đủ thời gian.</p>',
                'hint' => 'Kháng sinh có hoạt tính tiết niệu.',
                'tip' => '<p>Tái đánh giá nếu không cải thiện sau 48–72 giờ.</p>',
                'difficulty' => Difficulty::Easy,
                'options' => ['Kháng sinh thích hợp', 'Chỉ giảm đau không kháng sinh', 'Phẫu thuật ngay luôn', 'Xạ trị'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Thiếu máu thiếu sắt: điều trị bổ sung sắt đường uống thường cần theo dõi đáp ứng bằng gì sớm?</p>',
                'explanation' => '<p>Tăng reticulocyte / cải thiện Hb sau vài tuần là đáp ứng sớm.</p>',
                'hint' => 'Đáp ứng tạo máu.',
                'tip' => '<p>Tiếp tục sắt đủ thời gian để dự trữ ferritin phục hồi.</p>',
                'difficulty' => Difficulty::Medium,
                'options' => ['Theo dõi Hb/đáp ứng tạo máu', 'Chỉ nhìn da', 'Không cần xét nghiệm lại', 'Đo acid uric'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Gout cấp: thuốc nào thường dùng cắt viêm trong đợt cấp (không chống chỉ định)?</p>',
                'explanation' => '<p>NSAID, colchicine hoặc corticoid là các lựa chọn cắt viêm cấp.</p>',
                'hint' => 'Điều trị triệu chứng viêm khớp cấp.',
                'tip' => '<p>Allopurinol không khởi trị trong đợt cấp đang viêm nặng trừ chiến lược đặc biệt.</p>',
                'difficulty' => Difficulty::Easy,
                'options' => ['NSAID/colchicine/corticoid', 'Chỉ allopurinol khởi ngay trong mọi đợt cấp', 'Warfarin', 'Insulin'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Viêm khớp dạng thấp: nhóm thuốc nền (DMARD) kinh điển thường khởi đầu là gì?</p>',
                'explanation' => '<p>Methotrexate là DMARD nền tảng phổ biến khi không chống chỉ định.</p>',
                'hint' => 'DMARD kinh điển.',
                'tip' => '<p>Bổ sung acid folic và theo dõi độc tính.</p>',
                'difficulty' => Difficulty::Medium,
                'options' => ['Methotrexate (khi phù hợp)', 'Chỉ paracetamol dài hạn', 'Chỉ kháng sinh', 'Vitamin D đơn độc'],
                'correct' => 0,
            ],
            [
                'stem' => '<p>Loãng xương: biện pháp không thuốc quan trọng để giảm nguy cơ gãy xương?</p>',
                'explanation' => '<p>Tập kháng trở / chịu lực, dự phòng té ngã, đủ calcium–vitamin D.</p>',
                'hint' => 'Dự phòng té ngã và tập luyện.',
                'tip' => '<p>Kết hợp thuốc chống hủy xương khi có chỉ định densitometry/lâm sàng.</p>',
                'difficulty' => Difficulty::Easy,
                'options' => ['Tập luyện + dự phòng té ngã', 'Nằm bất động dài ngày', 'Uống rượu nhiều hơn', 'Ngưng mọi vận động'],
                'correct' => 0,
            ],
        ];

        return array_slice($items, 0, self::TARGET);
    }
}
