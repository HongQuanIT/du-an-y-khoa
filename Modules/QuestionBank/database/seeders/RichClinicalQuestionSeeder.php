<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders;

use App\Support\TargetExams;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionScopeType;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\MedicalTaxonomy;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionHint;
use Modules\QuestionBank\Models\QuestionOption;

/** Add 200 complete, classified clinical questions for local/demo use. */
final class RichClinicalQuestionSeeder extends Seeder
{
    private const QUESTION_COUNT = 200;

    public function run(): void
    {
        $this->call(MedicalKnowledgeTaxonomySeeder::class);
        $taxonomy = MedicalTaxonomy::query()
            ->where('code', MedicalKnowledgeTaxonomySeeder::TAXONOMY_CODE)
            ->firstOrFail();
        $topics = $this->topics($taxonomy);

        Question::withoutSyncingToSearch(function () use ($topics): void {
            DB::transaction(function () use ($topics): void {
                $cases = $this->clinicalCases();

                for ($index = 1; $index <= self::QUESTION_COUNT; $index++) {
                    $case = $cases[($index - 1) % count($cases)];
                    $variant = intdiv($index - 1, count($cases)) + 1;
                    $age = $case['age'] + (($variant - 1) % 5);
                    $code = sprintf('RICH-CLIN-%03d', $index);
                    $question = Question::query()->updateOrCreate(
                        ['code' => $code],
                        [
                            'stem' => "Bệnh nhân {$age} tuổi, {$case['presentation']} {$case['question']} (Tình huống {$variant})",
                            'explanation' => $case['explanation'],
                            'key_info' => [$case['key_info']],
                            'attending_tip' => $case['hint'],
                            'difficulty' => $this->difficulty($index),
                            'status' => QuestionStatus::Published,
                            'is_free' => $index % 4 === 0,
                            'exam_flag' => $index % 5 === 0,
                            'version' => 1,
                        ],
                    );

                    $this->options($question, $case['options'], $case['correct']);
                    QuestionHint::query()->updateOrCreate(
                        ['question_id' => $question->getKey(), 'sort_order' => 0],
                        ['content' => $case['hint'], 'status' => TaxonomyStatus::Active],
                    );

                    $system = $topics[$case['system_slug']];
                    $specialty = $topics[$case['specialty_slug']];
                    $question->medicalTaxonomyNodes()->sync([
                        $system->getKey() => ['relationship_type' => 'primary', 'is_primary' => true],
                        $specialty->getKey() => ['relationship_type' => 'secondary', 'is_primary' => false],
                    ]);

                    foreach (array_keys(TargetExams::selectable()) as $examKey) {
                        $question->scopes()->updateOrCreate([
                            'scope_type' => QuestionScopeType::Exam,
                            'scope_key' => $examKey,
                        ]);
                    }
                }
            });
        });

        $this->command?->info('Đã seed 200 câu hỏi lâm sàng đầy đủ đáp án, gợi ý, giải thích và phân loại.');
    }

    /** @return array<string, MedicalTaxonomyNode> */
    private function topics(MedicalTaxonomy $taxonomy): array
    {
        $definitions = [
            ['he-tim-mach-rich', 'Hệ tim mạch', 'tim-mach-rich', 'Tim mạch'],
            ['he-ho-hap-rich', 'Hệ hô hấp', 'ho-hap-rich', 'Hô hấp'],
            ['he-tieu-hoa-rich', 'Hệ tiêu hóa', 'tieu-hoa-rich', 'Tiêu hóa'],
            ['he-than-kinh-rich', 'Hệ thần kinh', 'than-kinh-rich', 'Thần kinh'],
            ['he-noi-tiet-rich', 'Hệ nội tiết', 'noi-tiet-rich', 'Nội tiết'],
            ['he-than-tiet-nieu-rich', 'Hệ thận và tiết niệu', 'than-tiet-nieu-rich', 'Thận học'],
            ['he-huyet-hoc-rich', 'Hệ huyết học', 'huyet-hoc-rich', 'Huyết học'],
            ['benh-truyen-nhiem-rich', 'Bệnh truyền nhiễm', 'truyen-nhiem-rich', 'Truyền nhiễm'],
            ['san-phu-khoa-rich', 'Sản phụ khoa', 'san-khoa-rich', 'Sản khoa'],
            ['nhi-khoa-rich', 'Nhi khoa', 'nhi-khoa-specialty-rich', 'Nhi tổng quát'],
        ];
        $topics = [];

        foreach ($definitions as $sort => [$systemSlug, $systemName, $specialtySlug, $specialtyName]) {
            $system = MedicalTaxonomyNode::query()->updateOrCreate(
                ['medical_taxonomy_id' => $taxonomy->getKey(), 'slug' => $systemSlug],
                [
                    'parent_id' => null,
                    'name' => $systemName,
                    'code' => $systemSlug,
                    'node_type' => 'system',
                    'sort_order' => 100 + $sort,
                    'status' => TaxonomyStatus::Active,
                ],
            );
            $specialty = MedicalTaxonomyNode::query()->updateOrCreate(
                ['medical_taxonomy_id' => $taxonomy->getKey(), 'slug' => $specialtySlug],
                [
                    'parent_id' => $system->getKey(),
                    'name' => $specialtyName,
                    'code' => $specialtySlug,
                    'node_type' => 'specialty',
                    'sort_order' => 100 + $sort,
                    'status' => TaxonomyStatus::Active,
                ],
            );
            $topics[$systemSlug] = $system;
            $topics[$specialtySlug] = $specialty;
        }

        return $topics;
    }

    /** @param list<string> $options */
    private function options(Question $question, array $options, int $correct): void
    {
        foreach ($options as $order => $content) {
            QuestionOption::query()->updateOrCreate(
                ['question_id' => $question->getKey(), 'label' => chr(65 + $order)],
                [
                    'content' => $content,
                    'is_correct' => $order === $correct,
                    'explanation' => $order === $correct
                        ? 'Đây là lựa chọn phù hợp nhất với dữ kiện lâm sàng.'
                        : 'Lựa chọn này không phù hợp nhất với dữ kiện đang có.',
                    'order' => $order,
                ],
            );
        }
    }

    private function difficulty(int $index): Difficulty
    {
        return match ($index % 5) {
            0 => Difficulty::Hard,
            1, 2 => Difficulty::Easy,
            default => Difficulty::Medium,
        };
    }

    /** @return list<array<string, mixed>> */
    private function clinicalCases(): array
    {
        return [
            [
                'system_slug' => 'he-tim-mach-rich', 'specialty_slug' => 'tim-mach-rich', 'age' => 58,
                'presentation' => 'đau thắt ngực 90 phút, vã mồ hôi; ECG có ST chênh lên ở DII, DIII và aVF.',
                'question' => 'Xử trí tái tưới máu ưu tiên là gì?',
                'options' => ['Can thiệp động mạch vành qua da cấp cứu', 'Theo dõi men tim trong 24 giờ', 'Nghiệm pháp gắng sức', 'Chỉ dùng thuốc giảm đau'],
                'correct' => 0,
                'hint' => 'ST chênh lên kèm đau ngực cấp là tình huống cần tái tưới máu càng sớm càng tốt.',
                'key_info' => 'STEMI cần kích hoạt quy trình PCI cấp cứu nếu có thể thực hiện kịp thời.',
                'explanation' => 'Bệnh cảnh phù hợp STEMI thành dưới. Can thiệp mạch vành qua da cấp cứu là phương pháp tái tưới máu ưu tiên khi có thể triển khai trong thời gian khuyến cáo.',
            ],
            [
                'system_slug' => 'he-ho-hap-rich', 'specialty_slug' => 'ho-hap-rich', 'age' => 24,
                'presentation' => 'khò khè, khó thở sau tiếp xúc bụi; nghe phổi có ran rít lan tỏa, SpO₂ 94%.',
                'question' => 'Điều trị ban đầu phù hợp nhất là gì?',
                'options' => ['Salbutamol dạng hít tác dụng ngắn', 'Kháng sinh phổ rộng', 'Lợi tiểu quai', 'Thuốc chống đông'],
                'correct' => 0,
                'hint' => 'Đợt hen cấp cần thuốc giãn phế quản tác dụng nhanh.',
                'key_info' => 'SABA dạng hít là điều trị cắt cơn ban đầu trong đợt hen cấp.',
                'explanation' => 'Triệu chứng và ran rít phù hợp đợt cấp hen phế quản. Salbutamol dạng hít giúp giãn phế quản nhanh và là lựa chọn ban đầu.',
            ],
            [
                'system_slug' => 'he-tieu-hoa-rich', 'specialty_slug' => 'tieu-hoa-rich', 'age' => 46,
                'presentation' => 'nôn ra máu, mạch 112 lần/phút và huyết áp 88/56 mmHg.',
                'question' => 'Bước xử trí đầu tiên phù hợp nhất là gì?',
                'options' => ['Thiết lập hai đường truyền lớn và hồi sức tuần hoàn', 'Nội soi đại tràng ngay', 'Cho bệnh nhân về theo dõi', 'Dùng thuốc nhuận tràng'],
                'correct' => 0,
                'hint' => 'Ưu tiên ổn định ABC trước khi thực hiện thủ thuật chẩn đoán.',
                'key_info' => 'Xuất huyết tiêu hóa có sốc cần hồi sức tuần hoàn trước nội soi.',
                'explanation' => 'Bệnh nhân có xuất huyết tiêu hóa trên kèm mất ổn định huyết động. Cần thiết lập đường truyền lớn, hồi sức dịch và chuẩn bị chế phẩm máu trước các bước tiếp theo.',
            ],
            [
                'system_slug' => 'he-than-kinh-rich', 'specialty_slug' => 'than-kinh-rich', 'age' => 67,
                'presentation' => 'đột ngột yếu nửa người phải và nói khó trong 45 phút.',
                'question' => 'Cận lâm sàng hình ảnh đầu tiên cần thực hiện là gì?',
                'options' => ['CT sọ não không cản quang', 'MRI cột sống thắt lưng', 'X-quang ngực', 'Siêu âm ổ bụng'],
                'correct' => 0,
                'hint' => 'Cần loại trừ xuất huyết não nhanh trước khi cân nhắc tái tưới máu.',
                'key_info' => 'CT sọ não không cản quang là hình ảnh ban đầu trong đột quỵ cấp.',
                'explanation' => 'Bệnh cảnh gợi ý đột quỵ cấp. CT sọ não không cản quang giúp phân biệt xuất huyết và thiếu máu não để quyết định điều trị tái tưới máu.',
            ],
            [
                'system_slug' => 'he-noi-tiet-rich', 'specialty_slug' => 'noi-tiet-rich', 'age' => 29,
                'presentation' => 'đái tháo đường típ 1, đau bụng, thở Kussmaul, glucose 420 mg/dL và pH 7,18.',
                'question' => 'Can thiệp đầu tiên phù hợp nhất là gì?',
                'options' => ['Truyền dung dịch NaCl 0,9%', 'Tiêm bicarbonat thường quy', 'Dùng metformin', 'Hạn chế dịch hoàn toàn'],
                'correct' => 0,
                'hint' => 'Trong nhiễm toan ceton, thiếu dịch tuần hoàn thường rất đáng kể.',
                'key_info' => 'Hồi sức dịch đẳng trương là bước đầu tiên trong điều trị DKA.',
                'explanation' => 'Bệnh nhân bị nhiễm toan ceton do đái tháo đường. Truyền dịch đẳng trương được bắt đầu trước, sau đó đánh giá kali và dùng insulin phù hợp.',
            ],
            [
                'system_slug' => 'he-than-tiet-nieu-rich', 'specialty_slug' => 'than-tiet-nieu-rich', 'age' => 7,
                'presentation' => 'phù toàn thân, protein niệu nhiều, albumin máu giảm và chức năng thận bình thường.',
                'question' => 'Chẩn đoán thường gặp nhất là gì?',
                'options' => ['Bệnh cầu thận thay đổi tối thiểu', 'Viêm bể thận cấp', 'Sỏi niệu quản', 'Hoại tử ống thận cấp'],
                'correct' => 0,
                'hint' => 'Hội chứng thận hư ở trẻ em thường do một bệnh đáp ứng tốt với corticosteroid.',
                'key_info' => 'Bệnh cầu thận thay đổi tối thiểu là nguyên nhân thường gặp nhất của hội chứng thận hư trẻ em.',
                'explanation' => 'Phù, protein niệu nhiều và giảm albumin phù hợp hội chứng thận hư. Ở trẻ em, nguyên nhân thường gặp nhất là bệnh cầu thận thay đổi tối thiểu.',
            ],
            [
                'system_slug' => 'he-huyet-hoc-rich', 'specialty_slug' => 'huyet-hoc-rich', 'age' => 35,
                'presentation' => 'mệt mỏi, da nhợt; Hb 8,9 g/dL, MCV 68 fL và ferritin giảm.',
                'question' => 'Chẩn đoán phù hợp nhất là gì?',
                'options' => ['Thiếu máu thiếu sắt', 'Thiếu máu bất sản', 'Tan máu tự miễn', 'Đa hồng cầu nguyên phát'],
                'correct' => 0,
                'hint' => 'Ferritin phản ánh dự trữ sắt và thường giảm trong thiếu sắt thật sự.',
                'key_info' => 'Thiếu máu hồng cầu nhỏ kèm ferritin thấp gợi ý thiếu máu thiếu sắt.',
                'explanation' => 'MCV thấp cho thấy thiếu máu hồng cầu nhỏ; ferritin giảm xác nhận dự trữ sắt thấp, phù hợp thiếu máu thiếu sắt.',
            ],
            [
                'system_slug' => 'benh-truyen-nhiem-rich', 'specialty_slug' => 'truyen-nhiem-rich', 'age' => 42,
                'presentation' => 'sốt cao, đau đầu, cứng gáy và rối loạn ý thức.',
                'question' => 'Kháng sinh kinh nghiệm phù hợp cần được khởi trị là gì?',
                'options' => ['Ceftriaxone phối hợp vancomycin', 'Azithromycin đơn trị', 'Metronidazole đơn trị', 'Không cần kháng sinh'],
                'correct' => 0,
                'hint' => 'Viêm màng não do vi khuẩn là cấp cứu; không trì hoãn kháng sinh khi nghi ngờ cao.',
                'key_info' => 'Ceftriaxone và vancomycin là phác đồ kinh nghiệm thường dùng cho viêm màng não cộng đồng ở người lớn.',
                'explanation' => 'Tam chứng sốt, cứng gáy và rối loạn ý thức gợi ý viêm màng não. Cần khởi trị kháng sinh kinh nghiệm sớm sau khi lấy bệnh phẩm phù hợp.',
            ],
            [
                'system_slug' => 'san-phu-khoa-rich', 'specialty_slug' => 'san-khoa-rich', 'age' => 30,
                'presentation' => 'thai 35 tuần, huyết áp 170/112 mmHg, đau đầu và protein niệu.',
                'question' => 'Thuốc nào cần dùng để dự phòng co giật?',
                'options' => ['Magnesium sulfate', 'Diazepam uống kéo dài', 'Warfarin', 'Methotrexate'],
                'correct' => 0,
                'hint' => 'Tiền sản giật nặng cần dự phòng sản giật bằng thuốc đặc hiệu.',
                'key_info' => 'Magnesium sulfate được dùng để dự phòng và điều trị co giật do sản giật.',
                'explanation' => 'Huyết áp rất cao kèm triệu chứng thần kinh và protein niệu phù hợp tiền sản giật nặng. Magnesium sulfate giúp dự phòng co giật.',
            ],
            [
                'system_slug' => 'nhi-khoa-rich', 'specialty_slug' => 'nhi-khoa-specialty-rich', 'age' => 3,
                'presentation' => 'tiêu chảy cấp, khát nước, mắt hơi trũng nhưng vẫn uống được và không sốc.',
                'question' => 'Bù dịch phù hợp nhất là gì?',
                'options' => ['Dung dịch oresol đường uống', 'Truyền albumin thường quy', 'Nhịn uống hoàn toàn', 'Dùng lợi tiểu'],
                'correct' => 0,
                'hint' => 'Trẻ mất nước nhẹ đến vừa và còn uống được nên ưu tiên bù dịch đường uống.',
                'key_info' => 'ORS là lựa chọn đầu tay cho mất nước nhẹ đến vừa do tiêu chảy ở trẻ.',
                'explanation' => 'Trẻ có dấu hiệu mất nước nhưng chưa sốc và vẫn uống được. Bù oresol đường uống là biện pháp an toàn, hiệu quả và ưu tiên.',
            ],
        ];
    }
}
