<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\UserSeeder;
use Illuminate\Database\Seeder;
use Modules\Admin\Actions\CaptureQuestionVersionAction;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\InstructorReviewDecision;
use Modules\QuestionBank\Enums\QuestionReviewAction;
use Modules\QuestionBank\Enums\QuestionReviewStatus;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionInstructorReview;
use Modules\QuestionBank\Models\QuestionOption;
use Modules\QuestionBank\Models\QuestionReviewRequest;

/**
 * Seeds sample questions created by Content Editor and routed to Instructors for review.
 * Covers all review statuses:
 *  - Pending review (both instructors can see and decide)
 *  - Partially approved (Instructor 1 approved, Instructor 2 sees in pending, Instructor 1 sees in approved)
 *  - Fully approved / Pending publish (Both instructors approved)
 *  - Rejected by instructor (Instructor rejected with feedback reason)
 *  - Draft created by Editor
 *  - Update review on an existing published question (supports comparison diff)
 */
final class QuestionReviewWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure users exist
        $this->call(UserSeeder::class);

        $editor = User::where('email', 'editor@medlearn.local')->first();
        $instructor1 = User::where('email', 'instructor@medlearn.local')->first();
        $instructor2 = User::where('email', 'instructor2@medlearn.local')->first();

        if (! $editor || ! $instructor1 || ! $instructor2) {
            $this->command?->warn('QuestionReviewWorkflowSeeder: Thiếu tài khoản editor hoặc instructor.');
            return;
        }

        $lessons = Lesson::query()->orderBy('sort_order')->orderBy('id')->get();
        if ($lessons->isEmpty()) {
            $this->command?->warn('QuestionReviewWorkflowSeeder: Chưa có bài học nào — chạy MedicalKnowledgeTaxonomySeeder trước.');
            return;
        }

        Question::withoutSyncingToSearch(function () use ($editor, $instructor1, $instructor2, $lessons): void {
            $this->seedPendingQuestions($editor, $lessons);
            $this->seedPartiallyApprovedQuestion($editor, $instructor1, $lessons);
            $this->seedFullyApprovedQuestion($editor, $instructor1, $instructor2, $lessons);
            $this->seedRejectedQuestion($editor, $instructor1, $lessons);
            $this->seedDraftQuestion($editor, $lessons);
            $this->seedUpdateReviewQuestion($editor, $lessons);
        });

        $this->command?->info('QuestionReviewWorkflowSeeder: Đã tạo dữ liệu mẫu câu hỏi từ Editor gửi Giảng viên duyệt thành công.');
    }

    /**
     * 1. Questions awaiting first review (Pending).
     */
    private function seedPendingQuestions(User $editor, $lessons): void
    {
        $samples = [
            [
                'code' => 'QBANK-REV-PENDING-001',
                'stem' => '<p>Bệnh nhân nam 45 tuổi, nhập viện vì sốt cao liên tục 5 ngày, đau đầu dữ dội sau hốc mắt, đau mỏi cơ khớp toàn thân. Xét nghiệm công thức máu: Bạch cầu 2.8 G/L, Tiểu cầu 85 G/L, Hematocrit 44%. Dấu hiệu lacet (+). Xét nghiệm chẩn đoán nhanh phù hợp nhất ở thời điểm này là gì?</p>',
                'attending_tip' => '<p>Lưu ý theo dõi sát dấu hiệu cảnh báo vào giai đoạn nguy hiểm (ngày 3–7 của sốt Dengue).</p>',
                'difficulty' => Difficulty::Medium,
                'options' => [
                    ['A', 'Test nhanh Dengue NS1Ag và IgM', true, 'Đúng: Ngày thứ 5 có thể kết hợp tìm kháng nguyên NS1 và kháng thể IgM.'],
                    ['B', 'Cấy máu tìm vi khuẩn', false, 'Sai: Lâm sàng và công thức máu gợi ý nhiễm siêu vi Dengue rõ rệt.'],
                    ['C', 'Huyết thanh chẩn đoán Widal', false, 'Sai: Dùng cho thương hàn, không phù hợp bệnh cảnh.'],
                    ['D', 'Chụp X-quang ngực thẳng khẩn cấp', false, 'Sai: Không phải ưu tiên đầu tiên khi chưa có triệu chứng hô hấp.'],
                ],
                'note' => 'Em đã cập nhật guideline Sốt xuất huyết Dengue mới nhất của Bộ Y tế, nhờ Thầy Cô duyệt giúp em ạ.',
            ],
            [
                'code' => 'QBANK-REV-PENDING-002',
                'stem' => '<p>Một phụ nữ 28 tuổi đến khám vì hồi hộp, sụt 4 kg trong 1 tháng dù ăn ngon miệng, run tay và sợ nóng. Khám thấy tuyến giáp to lan tỏa độ II, không đau, mạch 110 lần/phút, huyết áp 140/80 mmHg. Xét nghiệm TSH 0.01 µIU/mL, FT4 tăng cao. Chỉ số nào sau đây có giá trị đặc hiệu nhất để xác định nguyên nhân bệnh Basedow?</p>',
                'attending_tip' => '<p>TRAb là tiêu chuẩn vàng tự miễn, cũng giúp tiên lượng nguy cơ tái phát sau ngừng thuốc kháng giáp.</p>',
                'difficulty' => Difficulty::Easy,
                'options' => [
                    ['A', 'Kháng thể kháng thụ thể TSH (TRAb)', true, 'Đúng: TRAb là kháng thể kích thích trực tiếp thụ thể TSH gây bệnh Basedow.'],
                    ['B', 'Anti-TPO', false, 'Sai: Anti-TPO gặp nhiều trong viêm tuyến giáp Hashimoto.'],
                    ['C', 'Siêu âm Doppler màu tuyến giáp đơn thuần', false, 'Sai: Hỗ trợ đánh giá tưới máu nhưng không đặc hiệu bằng TRAb.'],
                    ['D', 'Đo nồng độ Calcitonin huyết thanh', false, 'Sai: Calcitonin là marker ung thư giáp thể tủy.'],
                ],
                'note' => 'Câu hỏi tình huống cường giáp Basedow - mức độ cơ bản cho sinh viên Y3.',
            ],
        ];

        foreach ($samples as $idx => $sample) {
            $lesson = $lessons[$idx % $lessons->count()];
            $question = $this->upsertQuestionBase($sample['code'], [
                'stem' => $sample['stem'],
                'attending_tip' => $sample['attending_tip'],
                'difficulty' => $sample['difficulty'],
                'status' => QuestionStatus::InReview,
                'created_by' => $editor->id,
                'version' => 0,
                'published_version' => 0,
                'instructor_review_cycle' => 1,
                'instructor_1_id' => null,
                'instructor_1_decision' => null,
                'instructor_2_id' => null,
                'instructor_2_decision' => null,
                'instructor_id' => null,
                'rejection_reason' => null,
                'rejected_by_role' => null,
            ]);

            $question->lessons()->sync([$lesson->id]);
            $this->syncOptions($question, $sample['options']);

            $this->upsertReviewRequest($question, $editor, QuestionReviewAction::Create, QuestionReviewStatus::Pending, $sample['note']);
        }
    }

    /**
     * 2. Partially approved: Instructor 1 has approved, waiting for Instructor 2.
     */
    private function seedPartiallyApprovedQuestion(User $editor, User $instructor1, $lessons): void
    {
        $code = 'QBANK-REV-PARTIAL-001';
        $lesson = $lessons[2 % $lessons->count()];

        $question = $this->upsertQuestionBase($code, [
            'stem' => '<p>Bệnh nhân nữ 60 tuổi vào viện vì khó thở khi nằm và phù hai chi dưới. Tiền sử đái tháo đường type 2 và tăng huyết áp 10 năm. Siêu âm tim cho thấy phân suất tống máu thất trái (LVEF) là 35%. Thuốc nào sau đây KHÔNG NÊN khởi đầu ngay trong giai đoạn suy tim mất bù cấp đang ứ dịch nặng?</p>',
            'attending_tip' => '<p>Nguyên tắc vàng: Chỉ bắt đầu chẹn beta khi bệnh nhân suy tim đã đạt trạng thái "khô" (euvolemic).</p>',
            'difficulty' => Difficulty::Hard,
            'status' => QuestionStatus::InReview,
            'created_by' => $editor->id,
            'version' => 0,
            'published_version' => 0,
            'instructor_review_cycle' => 1,
            'instructor_1_id' => $instructor1->id,
            'instructor_1_decision' => InstructorReviewDecision::Approved->value,
            'instructor_2_id' => null,
            'instructor_2_decision' => null,
            'instructor_id' => $instructor1->id,
            'rejection_reason' => null,
            'rejected_by_role' => null,
        ]);

        $question->lessons()->sync([$lesson->id]);
        $this->syncOptions($question, [
            ['A', 'Thuốc chẹn beta giao cảm (Bisoprolol/Carvedilol)', true, 'Đúng: Tránh khởi đầu chẹn beta khi đang mất bù cấp ứ dịch nặng.'],
            ['B', 'Lợi tiểu quai (Furosemide đường tĩnh mạch)', false, 'Sai: Đây là thuốc nền tảng để giải quyết ứ dịch.'],
            ['C', 'Thuốc ức chế SGLT2 (Dapagliflozin)', false, 'Sai: Có thể sử dụng sớm và an toàn trong suy tim cấp ổn định huyết động.'],
            ['D', 'Thuốc giãn mạch Nitrate ngậm dưới lưỡi', false, 'Sai: Chỉ định tốt nếu huyết áp còn cho phép để giảm tiền gánh.'],
        ]);

        $this->upsertReviewRequest($question, $editor, QuestionReviewAction::Create, QuestionReviewStatus::Pending, 'Câu hỏi chuyên sâu tim mạch ESC 2023.');

        // Add instructor 1 review log
        QuestionInstructorReview::query()->updateOrCreate(
            [
                'question_id' => $question->id,
                'review_cycle' => 1,
                'instructor_id' => $instructor1->id,
            ],
            [
                'decision' => InstructorReviewDecision::Approved,
                'note' => 'Nội dung ca lâm sàng chuẩn xác, bám sát khuyến cáo ESC. Đã duyệt phiếu 1.',
                'reviewed_at' => now()->subHours(3),
            ]
        );
    }

    /**
     * 3. Fully approved by both instructors: Status is PendingPublish.
     */
    private function seedFullyApprovedQuestion(User $editor, User $instructor1, User $instructor2, $lessons): void
    {
        $code = 'QBANK-REV-APPROVED-001';
        $lesson = $lessons[3 % $lessons->count()];

        $question = $this->upsertQuestionBase($code, [
            'stem' => '<p>Một người đàn ông 55 tuổi nghiện rượu nặng nhiều năm được đưa vào cấp cứu trong tình trạng lơ mơ, mất điều hòa động tác và liệt vận nhãn ngoài (liệt dây thần kinh số VI hai bên). Chẩn đoán hội chứng Wernicke được đặt ra. Biện pháp xử trí cấp cứu ĐẦU TIÊN và quan trọng nhất trước khi truyền dịch glucose là gì?</p>',
            'attending_tip' => '<p>Quy tắc sống còn trong cấp cứu: Luôn tiêm Thiamine TRƯỚC KHI truyền Glucose ở bệnh nhân nghi ngờ Wernicke!</p>',
            'difficulty' => Difficulty::Medium,
            'status' => QuestionStatus::PendingPublish,
            'created_by' => $editor->id,
            'version' => 0,
            'published_version' => 0,
            'instructor_review_cycle' => 1,
            'instructor_1_id' => $instructor1->id,
            'instructor_1_decision' => InstructorReviewDecision::Approved->value,
            'instructor_2_id' => $instructor2->id,
            'instructor_2_decision' => InstructorReviewDecision::Approved->value,
            'instructor_id' => $instructor2->id,
            'rejection_reason' => null,
            'rejected_by_role' => null,
        ]);

        $question->lessons()->sync([$lesson->id]);
        $this->syncOptions($question, [
            ['A', 'Tiêm tĩnh mạch Thiamine (Vitamin B1) liều cao', true, 'Đúng: Bắt buộc tiêm Thiamine trước để tránh khởi phát cơn tổn thương não cấp.'],
            ['B', 'Truyền dịch Glucose 10% tốc độ nhanh', false, 'Sai: Nguy hiểm, có thể làm cạn kiệt thiamine và làm bệnh nặng lên đột ngột.'],
            ['C', 'Tiêm bắp Vitamin B12 liều cao', false, 'Sai: B12 không giải quyết tình trạng thiếu thiamine cấp tính.'],
            ['D', 'Cho dùng Naloxone giải độc cấp', false, 'Sai: Không có dấu hiệu ngộ độc opioid.'],
        ]);

        $this->upsertReviewRequest($question, $editor, QuestionReviewAction::Create, QuestionReviewStatus::Pending, 'Ca lâm sàng cấp cứu thần kinh - dinh dưỡng kinh điển.');

        QuestionInstructorReview::query()->updateOrCreate(
            ['question_id' => $question->id, 'review_cycle' => 1, 'instructor_id' => $instructor1->id],
            [
                'decision' => InstructorReviewDecision::Approved,
                'note' => 'Câu hỏi rất hay và có tính thực hành lâm sàng cao. Đồng ý thông qua.',
                'reviewed_at' => now()->subDay(),
            ]
        );

        QuestionInstructorReview::query()->updateOrCreate(
            ['question_id' => $question->id, 'review_cycle' => 1, 'instructor_id' => $instructor2->id],
            [
                'decision' => InstructorReviewDecision::Approved,
                'note' => 'Đã thẩm định lại các lựa chọn và phần giải thích. Đạt yêu cầu xuất bản.',
                'reviewed_at' => now()->subHours(5),
            ]
        );
    }

    /**
     * 4. Rejected by Instructor: Status is Rejected with reason.
     */
    private function seedRejectedQuestion(User $editor, User $instructor1, $lessons): void
    {
        $code = 'QBANK-REV-REJECTED-001';
        $lesson = $lessons[4 % $lessons->count()];
        $rejectionReason = 'Phần giải thích chưa phân biệt rõ ràng vì sao không chọn phương án B trong trường hợp có bệnh đồng mắc suy thận. Cần trích dẫn hướng dẫn chẩn đoán của Bộ Y tế.';

        $question = $this->upsertQuestionBase($code, [
            'stem' => '<p>Bệnh nhân nam 70 tuổi có tiền sử suy thận mạn giai đoạn 3b kèm gout cấp tái phát. Lựa chọn điều trị cắt cơn gout cấp an toàn và được ưu tiên nhất là gì?</p>',
            'attending_tip' => '<p>Cần chú ý chức năng thận khi kê đơn thuốc kháng viêm trong cơn gout cấp.</p>',
            'difficulty' => Difficulty::Medium,
            'status' => QuestionStatus::Rejected,
            'created_by' => $editor->id,
            'version' => 0,
            'published_version' => 0,
            'instructor_review_cycle' => 1,
            'instructor_1_id' => $instructor1->id,
            'instructor_1_decision' => InstructorReviewDecision::Rejected->value,
            'instructor_2_id' => null,
            'instructor_2_decision' => null,
            'instructor_id' => $instructor1->id,
            'rejection_reason' => $rejectionReason,
            'rejected_by_role' => Role::Instructor->value,
        ]);

        $question->lessons()->sync([$lesson->id]);
        $this->syncOptions($question, [
            ['A', 'Corticosteroid đường uống ngắn ngày', true, 'Đúng: An toàn hơn NSAID và Colchicine trên bệnh nhân suy thận mạn.'],
            ['B', 'Colchicine liều cao nạp nhanh', false, 'Sai: Dễ gây ngộ độc và tích lũy khi suy giảm chức năng thận.'],
            ['C', 'Indomethacin liều tối đa', false, 'Sai: Nguy cơ suy thận cấp trên nền mạn.'],
            ['D', 'Allopurinol khởi đầu ngay trong cơn cấp', false, 'Sai: Không bắt đầu hạ acid uric khi cơn viêm khớp cấp đang rầm rộ.'],
        ]);

        // Review request is marked as rejected
        $this->upsertReviewRequest($question, $editor, QuestionReviewAction::Create, QuestionReviewStatus::Rejected, 'Gửi duyệt câu gout trên bệnh nhân suy thận.');

        QuestionInstructorReview::query()->updateOrCreate(
            ['question_id' => $question->id, 'review_cycle' => 1, 'instructor_id' => $instructor1->id],
            [
                'decision' => InstructorReviewDecision::Rejected,
                'note' => $rejectionReason,
                'reviewed_at' => now()->subHours(12),
            ]
        );
    }

    /**
     * 5. Draft question created by Editor (not submitted yet).
     */
    private function seedDraftQuestion(User $editor, $lessons): void
    {
        $code = 'QBANK-REV-DRAFT-001';
        $lesson = $lessons[5 % $lessons->count()];

        $question = $this->upsertQuestionBase($code, [
            'stem' => '<p>[Bản nháp] Bé gái 18 tháng tuổi sốt phát ban dạng sởi ngày thứ 4. Cần bổ sung vi chất nào để giảm tỷ lệ biến chứng và tử vong?</p>',
            'attending_tip' => '<p>Uống Vitamin A ngày 1 và ngày 2 ngay khi chẩn đoán sởi.</p>',
            'difficulty' => Difficulty::Easy,
            'status' => QuestionStatus::Draft,
            'created_by' => $editor->id,
            'version' => 0,
            'published_version' => 0,
            'instructor_review_cycle' => 0,
            'instructor_1_id' => null,
            'instructor_1_decision' => null,
            'instructor_2_id' => null,
            'instructor_2_decision' => null,
            'instructor_id' => null,
            'rejection_reason' => null,
            'rejected_by_role' => null,
        ]);

        $question->lessons()->sync([$lesson->id]);
        $this->syncOptions($question, [
            ['A', 'Vitamin A', true, 'Đúng: Phác đồ chuẩn của WHO.'],
            ['B', 'Vitamin C', false, 'Sai: Không có bằng chứng giảm biến chứng sởi.'],
            ['C', 'Kẽm (Zinc)', false, 'Sai: Dùng trong tiêu chảy, không đặc hiệu cho sởi.'],
            ['D', 'Sắt (Iron)', false, 'Sai: Không chỉ định cấp tính trong sốt phát ban sởi.'],
        ]);
    }

    /**
     * 6. Update review on an existing published question (for diff preview in review).
     */
    private function seedUpdateReviewQuestion(User $editor, $lessons): void
    {
        $code = 'QBANK-REV-UPDATE-001';
        $lesson = $lessons[6 % $lessons->count()];

        $question = $this->upsertQuestionBase($code, [
            'stem' => '<p>Bệnh nhân nam 30 tuổi, vào viện vì chấn thương ngực kín. Khám thấy tam chứng Beck điển hình. Tam chứng Beck bao gồm những triệu chứng nào sau đây?</p>',
            'attending_tip' => '<p>Chèn ép tim cấp là cấp cứu ngoại khoa khẩn cấp, cần chọc giải áp màng ngoài tim.</p>',
            'difficulty' => Difficulty::Easy,
            'status' => QuestionStatus::Published,
            'created_by' => $editor->id,
            'version' => 1,
            'published_version' => 1,
        ]);

        $question->lessons()->sync([$lesson->id]);
        $this->syncOptions($question, [
            ['A', 'Tụt huyết áp, tĩnh mạch cổ nổi, tiếng tim mờ', true, 'Đúng: Tam chứng kinh điển của chèn ép tim cấp.'],
            ['B', 'Tăng huyết áp, mạch chậm, thở không đều', false, 'Sai: Đây là tam chứng Cushing trong tăng áp lực nội sọ.'],
            ['C', 'Sốt, đau hạ sườn phải, vàng da', false, 'Sai: Đây là tam chứng Charcot trong viêm đường mật cấp.'],
            ['D', 'Đau bụng, trướng bụng, nôn ói', false, 'Sai: Hội chứng tắc ruột.'],
        ]);

        // Capture snapshot version 1
        $question = $question->fresh(['options', 'lessons']);
        app(CaptureQuestionVersionAction::class)->handle($question, $editor, 'publish');

        // Now Editor proposes an update (improving the clinical stem and difficulty to Medium)
        $question->forceFill([
            'stem' => '<p>Bệnh nhân nam 30 tuổi, vào viện sau tai nạn giao thông va đập vùng ngực vào vô lăng. Khám thấy huyết áp tụt 80/50 mmHg, tĩnh mạch cổ nổi to và nghe tim thấy tiếng tim mờ xa xăm (Tam chứng Beck). Chẩn đoán cấp cứu nào sau đây cần được xử trí can thiệp ngay lập tức?</p>',
            'difficulty' => Difficulty::Medium,
            'status' => QuestionStatus::InReview,
            'instructor_review_cycle' => 1,
            'instructor_1_id' => null,
            'instructor_1_decision' => null,
            'instructor_2_id' => null,
            'instructor_2_decision' => null,
            'instructor_id' => null,
        ])->save();

        $this->upsertReviewRequest($question, $editor, QuestionReviewAction::Update, QuestionReviewStatus::Pending, 'Cập nhật diễn đạt stem thực tế hơn theo ca tai nạn giao thông.');
    }

    private function upsertQuestionBase(string $code, array $attributes): Question
    {
        $question = Question::withTrashed()->where('code', $code)->first();
        if ($question !== null) {
            if ($question->trashed()) {
                $question->restore();
            }
            $question->forceFill($attributes)->save();
            return $question->fresh();
        }

        $question = new Question($attributes);
        $question->code = $code;
        $question->save();

        return $question->fresh();
    }

    /**
     * @param list<array{0: string, 1: string, 2: bool, 3: string}> $optionsData
     */
    private function syncOptions(Question $question, array $optionsData): void
    {
        $existing = $question->options()->orderBy('order')->get();

        foreach ($optionsData as $idx => [$label, $content, $isCorrect, $explanation]) {
            $payload = [
                'label' => $label,
                'content' => $content,
                'is_correct' => $isCorrect,
                'explanation' => $explanation,
                'order' => $idx + 1,
            ];

            if (isset($existing[$idx])) {
                $existing[$idx]->fill($payload)->save();
            } else {
                $question->options()->create($payload);
            }
        }

        if ($existing->count() > count($optionsData)) {
            $existing->slice(count($optionsData))->each->delete();
        }
    }

    private function upsertReviewRequest(
        Question $question,
        User $requester,
        QuestionReviewAction $action,
        QuestionReviewStatus $status,
        ?string $note = null
    ): void {
        QuestionReviewRequest::query()->updateOrCreate(
            [
                'question_id' => $question->id,
                'status' => $status->value,
            ],
            [
                'action' => $action,
                'requested_by' => $requester->id,
                'review_note' => $note,
                'reviewed_at' => $status === QuestionReviewStatus::Rejected ? now()->subHours(12) : null,
            ]
        );
    }
}
