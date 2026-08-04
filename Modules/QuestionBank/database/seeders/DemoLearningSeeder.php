<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionOption;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionStatus as UserQuestionStatusModel;
use Modules\QuestionBank\Models\Topic;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Fixed, human-readable demo dataset for the learning slice:
 * topics -> questions + options -> a student's sessions/attempts/status.
 *
 * Idempotent: safe to re-run (keys on slug/stem/email; skips progress if it
 * already exists). Scout syncing is disabled during the run.
 */
class DemoLearningSeeder extends Seeder
{
    public function run(): void
    {
        Question::withoutSyncingToSearch(function (): void {
            $this->call(TopicTaxonomySeeder::class);
            $topics = Topic::query()->get()->keyBy('slug')->all();
            $this->seedQuestions($topics);
            $student = $this->resolveDemoStudent();
            $this->seedProgress($student);
            $this->seedHintUsage($student);
        });
    }

    /**
     * Seed curated questions (with options) and top up to ~500 Amboss-style MCQs.
     *
     * @param  array<string, Topic>  $topics
     */
    private function seedQuestions(array $topics): void
    {
        foreach ($this->curatedQuestions() as $data) {
            $topic = $topics[$data['topic']] ?? null;

            $question = Question::firstOrCreate(
                ['stem' => $data['stem']],
                [
                    'explanation' => $data['explanation'] ?? null,
                    'difficulty' => $data['difficulty'],
                    'status' => QuestionStatus::Published,
                    'topic_id' => $topic?->id,
                    'is_free' => $data['is_free'] ?? false,
                ],
            );

            if ($question->wasRecentlyCreated) {
                $this->seedOptions($question, $data['options']);
            }
        }

        // Top up so a multi-week study plan can draw a fresh Amboss-style batch every day.
        $systemSlugs = Topic::query()
            ->where('type', 'system')
            ->orderBy('order')
            ->pluck('slug')
            ->all();

        if ($systemSlugs === []) {
            $systemSlugs = ['tim-mach', 'ho-hap', 'noi-tiet', 'tieu-hoa', 'chan-thuong', 'so-sinh', 'khang-sinh'];
        }

        $target = 500;
        $existing = Question::count();
        $bank = $this->ambossStyleBank();

        for ($i = $existing; $i < $target; $i++) {
            $slug = $systemSlugs[$i % count($systemSlugs)];
            $topic = $topics[$slug] ?? null;
            $template = $bank[$i % count($bank)];
            $difficulty = [Difficulty::Easy, Difficulty::Medium, Difficulty::Hard][$i % 3];
            $caseNo = $i + 1;
            $topicName = $topic?->name ?? 'Tổng hợp';

            $stem = sprintf(
                '[Amboss] Ca lâm sàng #%03d — %s. %s',
                $caseNo,
                $topicName,
                $template['stem'],
            );

            $question = Question::firstOrCreate(
                ['stem' => $stem],
                [
                    'explanation' => $template['explanation'].' (Chủ đề: '.$topicName.'; ca #'.$caseNo.'.)',
                    'difficulty' => $difficulty,
                    'status' => QuestionStatus::Published,
                    'topic_id' => $topic?->id,
                    'is_free' => $i % 3 === 0,
                ],
            );

            if ($question->wasRecentlyCreated) {
                $correctIndex = $i % 4;
                $right = $template['options'][0];
                $wrongs = array_values(array_slice($template['options'], 1));
                $options = [];
                $wrongCursor = 0;

                foreach (['A', 'B', 'C', 'D'] as $idx => $label) {
                    $isCorrect = $idx === $correctIndex;
                    $content = $isCorrect ? $right : $wrongs[$wrongCursor++];
                    $options[] = [
                        'content' => $content,
                        'is_correct' => $isCorrect,
                        'explanation' => $isCorrect
                            ? "Đáp án {$label} khớp cơ chế/lâm sàng Amboss cho tình huống này."
                            : "Đáp án {$label} không phù hợp với diễn tiến hoặc hướng xử trí ưu tiên.",
                    ];
                }
                $this->seedOptions($question, $options);
            }
        }
    }

    /**
     * Rotating Amboss-style Vietnamese clinical stems + 4 option texts.
     *
     * @return list<array{stem: string, explanation: string, options: list<string>}>
     */
    private function ambossStyleBank(): array
    {
        return [
            [
                'stem' => 'Bệnh nhân 58 tuổi đau ngực trái lan tay trái 40 phút, vã mồ hôi. Bước xử trí ưu tiên tiếp theo là gì?',
                'explanation' => 'Nghi STEMI: ECG sớm, aspirin, đánh giá tái tưới máu theo cửa sổ thời gian.',
                'options' => [
                    'Điện tâm đồ 12 chuyển đạo trong 10 phút',
                    'Chụp CT ngực có cản quang ngay',
                    'Đo D-dimer thường quy',
                    'Cho về theo dõi ngoại trú',
                ],
            ],
            [
                'stem' => 'Bệnh nhân hen đang dùng ICS+LABA vẫn khò khè ban đêm. Bổ sung nào hợp lý theo bậc điều trị?',
                'explanation' => 'Tăng bậc điều trị hoặc thêm kiểm soát viêm khi triệu chứng về đêm còn tồn tại.',
                'options' => [
                    'Thêm/tối ưu kháng leukotriene hoặc tăng ICS',
                    'Ngừng toàn bộ ICS',
                    'Chỉ dùng kháng histamine',
                    'Kháng sinh macrolide kéo dài mặc định',
                ],
            ],
            [
                'stem' => 'Người 45 tuổi HbA1c 7,8%, BMI 31, chưa biến chứng. Lựa chọn khởi trị nào phù hợp nhất?',
                'explanation' => 'Metformin vẫn là nền tảng khởi trị T2DM khi không chống chỉ định.',
                'options' => [
                    'Metformin + thay đổi lối sống',
                    'Insulin bolus ngay từ đầu',
                    'Chỉ chế độ ăn không thuốc',
                    'Sulfonylurea liều cao đơn trị',
                ],
            ],
            [
                'stem' => 'Đau bụng quanh rốn 12 giờ, sau khu trú hố chậu phải, sốt nhẹ, bạch cầu tăng. Chẩn đoán ưu tiên?',
                'explanation' => 'Diễn tiến điển hình của viêm ruột thừa cấp.',
                'options' => [
                    'Viêm ruột thừa cấp',
                    'Loét dạ dày thủng',
                    'Sỏi mật không biến chứng',
                    'Viêm tụy mạn',
                ],
            ],
            [
                'stem' => 'Thai 34 tuần, HA 170/110 mmHg, protein niệu (++), đau đầu. Hướng xử trí cấp nào đúng?',
                'explanation' => 'Tiền sản giật nặng: ổn định mẹ, magie sulfate phòng co giật, cân nhắc kết thúc thai kỳ.',
                'options' => [
                    'Magie sulfate + hạ áp + hội chẩn sản',
                    'Chỉ theo dõi ngoại trú',
                    'Lợi tiểu quai liều cao đơn độc',
                    'Ngừng theo dõi thai ngay tại nhà',
                ],
            ],
            [
                'stem' => 'Trẻ sơ sinh đủ tháng vàng da giờ thứ 18. Nhận định nào đúng nhất?',
                'explanation' => 'Vàng da <24h là bệnh lý, cần đánh giá tán huyết/nhiễm trùng.',
                'options' => [
                    'Vàng da bệnh lý — cần xét nghiệm ngay',
                    'Vàng da sinh lý bình thường',
                    'Chỉ theo dõi sau 1 tuần',
                    'Bổ sung sắt đường uống là đủ',
                ],
            ],
            [
                'stem' => 'Viêm phổi cộng đồng nhẹ, không bệnh nền. Kháng sinh ngoại trú phù hợp?',
                'explanation' => 'Amoxicillin hoặc macrolide theo hướng dẫn địa phương cho CAP nhẹ.',
                'options' => [
                    'Amoxicillin hoặc macrolide theo guideline',
                    'Vancomycin IV mặc định',
                    'Carbapenem phổ rộng',
                    'Không kháng sinh dù sốt cao',
                ],
            ],
            [
                'stem' => 'Đa chấn thương sau TNGT, chưa thông đường thở. Ưu tiên ABCDE bước đầu?',
                'explanation' => 'Airway kèm bảo vệ cột sống cổ là bước đầu tiên.',
                'options' => [
                    'Đảm bảo đường thở + bảo vệ cột sống cổ',
                    'Truyền máu khối lượng lớn trước tiên',
                    'Chụp MRI toàn thân ngay',
                    'Gây mê sâu không đánh giá đường thở',
                ],
            ],
            [
                'stem' => 'Bệnh nhân COPD đợt cấp, SpO2 84%, thở nhanh, lơ mơ. Biến chứng cấp cần nghĩ đến?',
                'explanation' => 'Suy hô hấp tăng CO2/giảm O2 là biến chứng nguy hiểm của đợt cấp nặng.',
                'options' => [
                    'Suy hô hấp cấp (tăng CO2/giảm O2)',
                    'Hạ đường huyết đơn thuần',
                    'Cơn gout cấp',
                    'Viêm tai giữa',
                ],
            ],
            [
                'stem' => 'Sốc phản vệ sau tiêm kháng sinh: mày đay, tụt HA, khó thở. Thuốc ưu tiên?',
                'explanation' => 'Adrenaline tiêm bắp là xử trí đầu tay trong sốc phản vệ.',
                'options' => [
                    'Adrenaline tiêm bắp đùi',
                    'Chỉ kháng histamine uống',
                    'Corticoid uống đơn độc',
                    'Truyền dịch chậm không thuốc',
                ],
            ],
            [
                'stem' => 'Tăng HA + đái tháo đường có albumin niệu. Nhóm thuốc hạ áp ưu tiên?',
                'explanation' => 'ACEi/ARB bảo vệ thận và là lựa chọn đầu tay khi có protein/albumin niệu.',
                'options' => [
                    'ACEi hoặc ARB',
                    'Chẹn beta không chọn lọc đơn độc',
                    'Lợi tiểu thẩm thấu',
                    'Nitrate kéo dài',
                ],
            ],
            [
                'stem' => 'Đau nửa đầu Pulsatile kèm buồn nôn, sợ ánh sáng, không dấu thần kinh khu trú. Chẩn đoán gần nhất?',
                'explanation' => 'Triệu chứng điển hình của migraine không kèm aura phức tạp.',
                'options' => [
                    'Đau nửa đầu (migraine)',
                    'Đột quỵ xuất huyết não',
                    'Viêm màng não mủ',
                    'Glôcôm góc đóng cấp',
                ],
            ],
            [
                'stem' => 'Thiếu máu hồng cầu nhỏ, ferritin thấp, MCV giảm. Nguyên nhân thường gặp nhất?',
                'explanation' => 'Thiếu sắt là nguyên nhân phổ biến của microcytic anemia.',
                'options' => [
                    'Thiếu sắt',
                    'Thiếu B12',
                    'Thiếu folate đơn thuần',
                    'Tăng hồng cầu nguyên phát',
                ],
            ],
            [
                'stem' => 'Sỏi niệu quản gây đau quặn thận, không sốt, CT không biến chứng. Xử trí ban đầu?',
                'explanation' => 'Giảm đau, dịch, lọc sỏi theo kích thước; không kháng sinh nếu không nhiễm khuẩn.',
                'options' => [
                    'Giảm đau + bù dịch + theo dõi/chỉ định lấy sỏi',
                    'Cắt thận cấp cứu mặc định',
                    'Kháng sinh phổ rộng kéo dài',
                    'Xạ trị ổ bụng',
                ],
            ],
            [
                'stem' => 'Trầm cảm nặng có ý tưởng tự sát rõ. Bước quản lý ưu tiên?',
                'explanation' => 'An toàn bệnh nhân và đánh giá nguy cơ tự sát là ưu tiên hàng đầu.',
                'options' => [
                    'Đảm bảo an toàn + đánh giá nguy cơ tự sát',
                    'Chỉ kê SSRI và cho về nhà ngay',
                    'Bắt buộc điện giật không đánh giá',
                    'Không cần theo dõi sát',
                ],
            ],
            [
                'stem' => 'Viêm khớp gối nóng đỏ, dịch đục, sốt. Xét nghiệm dịch khớp ưu tiên để loại trừ?',
                'explanation' => 'Viêm khớp nhiễm khuẩn cần chọc hút, nhuộm Gram/cấy dịch khớp khẩn.',
                'options' => [
                    'Chọc hút dịch khớp (Gram/cấy)',
                    'Chỉ CRP ngoại trú',
                    'MRI sau 1 tháng',
                    'Tiêm corticoid nội khớp ngay',
                ],
            ],
        ];
    }

    /**
     * @param  list<array{content: string, is_correct: bool, explanation?: string}>  $options
     */
    private function seedOptions(Question $question, array $options): void
    {
        $labels = ['A', 'B', 'C', 'D', 'E'];

        foreach (array_values($options) as $index => $option) {
            QuestionOption::create([
                'question_id' => $question->getKey(),
                'label' => $labels[$index] ?? (string) ($index + 1),
                'content' => $option['content'],
                'is_correct' => $option['is_correct'],
                'explanation' => $option['explanation'] ?? null,
                'order' => $index,
            ]);
        }
    }

    /**
     * Resolve (or create) the primary demo student to attach progress to.
     */
    private function resolveDemoStudent(): User
    {
        $student = User::firstOrCreate(
            ['email' => 'student@medlearn.local'],
            ['name' => 'Student', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );

        if (! $student->hasRole(Role::Student->value)) {
            RoleModel::findOrCreate(Role::Student->value, 'web');
            $student->assignRole(Role::Student->value);
        }

        return $student;
    }

    /**
     * Build two completed sessions and one paused session with attempts +
     * per-question status. Skips entirely if the student already has sessions.
     */
    private function seedProgress(User $student): void
    {
        if (QuestionSession::where('user_id', $student->id)->exists()) {
            return;
        }

        /** @var Collection<int, Question> $questions */
        $questions = Question::with('options')
            ->where('status', QuestionStatus::Published)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($questions->isEmpty()) {
            return;
        }

        // Session 1: 10 questions, 7 correct.
        $this->buildSession(
            $student,
            $questions->slice(0, 10)->values(),
            correctTarget: 7,
            status: SessionStatus::Completed,
            daysAgo: 5,
        );

        // Session 2: 10 questions, 5 correct.
        $this->buildSession(
            $student,
            $questions->slice(10, 10)->values(),
            correctTarget: 5,
            status: SessionStatus::Completed,
            daysAgo: 2,
        );

        // Session 3: paused after answering 3 of 8 (Continue Learning).
        $this->buildSession(
            $student,
            $questions->slice(20, 8)->values(),
            correctTarget: 2,
            status: SessionStatus::Paused,
            daysAgo: 0,
            answeredLimit: 3,
        );
    }

    /**
     * @param  Collection<int, Question>  $questions
     */
    private function buildSession(
        User $student,
        Collection $questions,
        int $correctTarget,
        SessionStatus $status,
        int $daysAgo,
        ?int $answeredLimit = null,
    ): void {
        $total = $questions->count();
        $answered = $answeredLimit ?? $total;
        $when = Carbon::now()->subDays($daysAgo);

        $session = QuestionSession::create([
            'user_id' => $student->id,
            'mode' => SessionMode::Study,
            'status' => $status,
            'filters' => ['status' => 'unseen'],
            'question_ids' => $questions->map(fn (Question $q) => $q->getKey())->all(),
            'total' => $total,
            'answered_count' => $answered,
            'correct_count' => 0,
            'time_limit_seconds' => null,
            'paused_state' => $status === SessionStatus::Paused ? ['current_index' => $answered] : null,
            'created_at' => $when,
            'updated_at' => $when,
        ]);

        $correctCount = 0;

        foreach ($questions->values() as $index => $question) {
            if ($index >= $answered) {
                break; // paused: leave remaining questions unanswered
            }

            $shouldBeCorrect = $index < $correctTarget;
            $option = $this->pickOption($question, $shouldBeCorrect);
            $isCorrect = $option?->is_correct ?? false;
            $correctCount += $isCorrect ? 1 : 0;

            QuestionAttempt::create([
                'session_id' => $session->getKey(),
                'user_id' => $student->id,
                'question_id' => $question->getKey(),
                'selected_option_ids' => $option ? [$option->id] : [],
                'is_correct' => $isCorrect,
                'used_hint' => false,
                'time_spent_seconds' => 45 + $index * 3,
                'confidence' => $isCorrect ? 'high' : 'low',
                'flagged' => false,
                'answered_at' => $when,
            ]);

            $this->upsertStatus($student, $question, $isCorrect, $when);
        }

        $session->forceFill(['correct_count' => $correctCount])->save();
    }

    private function pickOption(Question $question, bool $wantCorrect): ?QuestionOption
    {
        $options = $question->options;

        if ($options->isEmpty()) {
            return null;
        }

        $match = $options->first(fn (QuestionOption $o) => $o->is_correct === $wantCorrect);

        return $match ?? $options->first();
    }

    private function upsertStatus(User $student, Question $question, bool $isCorrect, Carbon $when): void
    {
        $status = $isCorrect ? UserQuestionStatus::Correct : UserQuestionStatus::Incorrect;

        UserQuestionStatusModel::updateOrCreate(
            ['user_id' => $student->id, 'question_id' => $question->getKey()],
            [
                'status' => $status,
                'attempts_count' => 1,
                'last_attempt_at' => $when,
                'last_correct_at' => $isCorrect ? $when : null,
            ],
        );
    }

    /**
     * Keep the "answered correctly using hints" filter usable in demo data.
     *
     * This also upgrades older seeded databases where all attempts originally
     * had used_hint=false.
     */
    private function seedHintUsage(User $student): void
    {
        $attemptIds = QuestionAttempt::query()
            ->where('user_id', $student->id)
            ->where('is_correct', true)
            ->orderByDesc('answered_at')
            ->orderByDesc('id')
            ->limit(8)
            ->pluck('id');

        QuestionAttempt::query()
            ->whereIn('id', $attemptIds)
            ->update(['used_hint' => true]);
    }

    /**
     * Curated, medically-flavoured MCQs (Vietnamese) across topics.
     *
     * @return list<array{stem: string, difficulty: Difficulty, is_free?: bool, topic: string, explanation?: string, options: list<array{content: string, is_correct: bool, explanation?: string}>}>
     */
    private function curatedQuestions(): array
    {
        return [
            [
                'stem' => 'Thuốc nào là lựa chọn đầu tay để kiểm soát cơn nhịp nhanh kịch phát trên thất (PSVT) ổn định huyết động?',
                'difficulty' => Difficulty::Medium,
                'is_free' => true,
                'topic' => 'tim-mach',
                'explanation' => 'Adenosine tác dụng nhanh, cắt vòng vào lại tại nút nhĩ thất, là lựa chọn đầu tay khi nghiệm pháp Vagal thất bại.',
                'options' => [
                    ['content' => 'Adenosine', 'is_correct' => true, 'explanation' => 'Cắt vòng vào lại tại nút AV.'],
                    ['content' => 'Amiodarone', 'is_correct' => false],
                    ['content' => 'Digoxin', 'is_correct' => false],
                    ['content' => 'Lidocaine', 'is_correct' => false],
                ],
            ],
            [
                'stem' => 'Dấu hiệu điện tâm đồ điển hình của nhồi máu cơ tim ST chênh lên (STEMI) là gì?',
                'difficulty' => Difficulty::Easy,
                'is_free' => true,
                'topic' => 'tim-mach',
                'options' => [
                    ['content' => 'ST chênh lên ≥ 1mm ở ≥ 2 chuyển đạo liên tiếp', 'is_correct' => true],
                    ['content' => 'Sóng T đảo đơn thuần', 'is_correct' => false],
                    ['content' => 'PR kéo dài', 'is_correct' => false],
                    ['content' => 'QT ngắn', 'is_correct' => false],
                ],
            ],
            [
                'stem' => 'Trong hen phế quản cấp, nhóm thuốc nào cho tác dụng giãn phế quản nhanh nhất?',
                'difficulty' => Difficulty::Easy,
                'topic' => 'ho-hap',
                'options' => [
                    ['content' => 'Cường beta-2 tác dụng ngắn (SABA)', 'is_correct' => true],
                    ['content' => 'Corticoid đường uống', 'is_correct' => false],
                    ['content' => 'Kháng leukotriene', 'is_correct' => false],
                    ['content' => 'Kháng IgE', 'is_correct' => false],
                ],
            ],
            [
                'stem' => 'Xét nghiệm nào giúp chẩn đoán xác định đái tháo đường theo tiêu chuẩn HbA1c?',
                'difficulty' => Difficulty::Medium,
                'topic' => 'noi-tiet',
                'options' => [
                    ['content' => 'HbA1c ≥ 6.5%', 'is_correct' => true],
                    ['content' => 'HbA1c ≥ 5.0%', 'is_correct' => false],
                    ['content' => 'Glucose đói ≥ 5.6 mmol/L', 'is_correct' => false],
                    ['content' => 'Glucose bất kỳ ≥ 7.0 mmol/L', 'is_correct' => false],
                ],
            ],
            [
                'stem' => 'Vị trí đau điển hình khi khởi phát viêm ruột thừa cấp thường ở đâu?',
                'difficulty' => Difficulty::Easy,
                'is_free' => true,
                'topic' => 'tieu-hoa',
                'options' => [
                    ['content' => 'Quanh rốn, sau đó khu trú hố chậu phải', 'is_correct' => true],
                    ['content' => 'Hạ sườn trái', 'is_correct' => false],
                    ['content' => 'Vùng thượng vị lan sau lưng', 'is_correct' => false],
                    ['content' => 'Hố chậu trái', 'is_correct' => false],
                ],
            ],
            [
                'stem' => 'Nguyên tắc xử trí ban đầu (ABCDE) ở bệnh nhân đa chấn thương ưu tiên bước nào đầu tiên?',
                'difficulty' => Difficulty::Medium,
                'topic' => 'chan-thuong',
                'options' => [
                    ['content' => 'Airway (đường thở) kèm bảo vệ cột sống cổ', 'is_correct' => true],
                    ['content' => 'Breathing', 'is_correct' => false],
                    ['content' => 'Circulation', 'is_correct' => false],
                    ['content' => 'Disability', 'is_correct' => false],
                ],
            ],
            [
                'stem' => 'Vàng da sinh lý ở trẻ sơ sinh đủ tháng thường xuất hiện vào thời điểm nào?',
                'difficulty' => Difficulty::Medium,
                'topic' => 'so-sinh',
                'options' => [
                    ['content' => 'Sau 24 giờ tuổi', 'is_correct' => true],
                    ['content' => 'Ngay trong 24 giờ đầu', 'is_correct' => false],
                    ['content' => 'Sau 2 tuần tuổi', 'is_correct' => false],
                    ['content' => 'Chỉ khi có bất đồng nhóm máu', 'is_correct' => false],
                ],
            ],
            [
                'stem' => 'Kháng sinh nhóm nào ức chế tổng hợp thành tế bào vi khuẩn?',
                'difficulty' => Difficulty::Easy,
                'is_free' => true,
                'topic' => 'khang-sinh',
                'options' => [
                    ['content' => 'Beta-lactam', 'is_correct' => true],
                    ['content' => 'Aminoglycoside', 'is_correct' => false],
                    ['content' => 'Macrolide', 'is_correct' => false],
                    ['content' => 'Fluoroquinolone', 'is_correct' => false],
                ],
            ],
            [
                'stem' => 'Dấu hiệu nào gợi ý tiền sản giật nặng ở thai phụ?',
                'difficulty' => Difficulty::Hard,
                'topic' => 'san-phu-khoa',
                'options' => [
                    ['content' => 'Huyết áp ≥ 160/110 mmHg kèm protein niệu', 'is_correct' => true],
                    ['content' => 'Phù mắt cá chân đơn thuần', 'is_correct' => false],
                    ['content' => 'Buồn nôn 3 tháng đầu', 'is_correct' => false],
                    ['content' => 'Tăng cân 1kg/tháng', 'is_correct' => false],
                ],
            ],
            [
                'stem' => 'Thuốc đầu tay điều trị tăng huyết áp ở bệnh nhân đái tháo đường có protein niệu là gì?',
                'difficulty' => Difficulty::Hard,
                'topic' => 'tim-mach',
                'options' => [
                    ['content' => 'Ức chế men chuyển (ACEi) hoặc chẹn thụ thể (ARB)', 'is_correct' => true],
                    ['content' => 'Lợi tiểu thiazide đơn thuần', 'is_correct' => false],
                    ['content' => 'Chẹn beta', 'is_correct' => false],
                    ['content' => 'Chẹn kênh canxi nhóm dihydropyridine', 'is_correct' => false],
                ],
            ],
            [
                'stem' => 'Biến chứng cấp nguy hiểm nhất của đợt cấp COPD nặng là gì?',
                'difficulty' => Difficulty::Medium,
                'topic' => 'ho-hap',
                'options' => [
                    ['content' => 'Suy hô hấp tăng CO2 máu', 'is_correct' => true],
                    ['content' => 'Tăng thân nhiệt', 'is_correct' => false],
                    ['content' => 'Hạ đường huyết', 'is_correct' => false],
                    ['content' => 'Tăng kali máu', 'is_correct' => false],
                ],
            ],
            [
                'stem' => 'Trong sốc phản vệ, thuốc và đường dùng ưu tiên đầu tiên là gì?',
                'difficulty' => Difficulty::Easy,
                'is_free' => true,
                'topic' => 'khang-sinh',
                'options' => [
                    ['content' => 'Adrenaline tiêm bắp', 'is_correct' => true],
                    ['content' => 'Corticoid tĩnh mạch', 'is_correct' => false],
                    ['content' => 'Kháng histamine uống', 'is_correct' => false],
                    ['content' => 'Adrenaline tĩnh mạch nhanh', 'is_correct' => false],
                ],
            ],
        ];
    }
}
