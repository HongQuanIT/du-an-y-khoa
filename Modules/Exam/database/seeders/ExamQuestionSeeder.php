<?php

declare(strict_types=1);

namespace Modules\Exam\Database\Seeders;

use App\Support\TargetExams;
use Illuminate\Database\Seeder;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionScopeType;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionOption;
use Modules\QuestionBank\Models\QuestionScope;
use Modules\QuestionBank\Models\Topic;

final class ExamQuestionSeeder extends Seeder
{
    public function run(): void
    {
        Question::withoutSyncingToSearch(function (): void {
            $topic = Topic::query()->updateOrCreate(
                ['slug' => 'exam-mock-cases'],
                [
                    'name' => 'Kỳ thi mô phỏng',
                    'type' => 'system',
                    'order' => 999,
                ],
            );

            $questions = [
                [
                    'stem' => 'Bệnh nhân nam 58 tuổi đau ngực đè nặng lan tay trái, vã mồ hôi lạnh. Chẩn đoán phù hợp nhất là gì?',
                    'options' => [
                        'Viêm thực quản trào ngược',
                        'Nhồi máu cơ tim cấp',
                        'Trào ngược dạ dày đơn thuần',
                        'Viêm phổi thùy dưới',
                    ],
                    'correct' => 1,
                    'explanation' => 'Đau ngực kiểu mạch vành, lan tay trái và vã mồ hôi là gợi ý điển hình cho nhồi máu cơ tim cấp.',
                    'difficulty' => Difficulty::Medium,
                ],
                [
                    'stem' => 'Bệnh nhân sốt cao, ho đàm, ran ẩm khu trú và X-quang phổi có thâm nhiễm thùy dưới. Chẩn đoán phù hợp nhất là gì?',
                    'options' => [
                        'Viêm phổi cộng đồng',
                        'Hen phế quản',
                        'Tràn khí màng phổi',
                        'Lao hạch',
                    ],
                    'correct' => 0,
                    'explanation' => 'Sốt, ho đàm, ran ẩm khu trú và thâm nhiễm thùy gợi ý viêm phổi cộng đồng.',
                    'difficulty' => Difficulty::Easy,
                ],
                [
                    'stem' => 'Người bệnh khát nhiều, tiểu nhiều, hơi thở có mùi trái cây, đường huyết rất cao và ceton dương tính. Chẩn đoán nào phù hợp nhất?',
                    'options' => [
                        'Hạ đường huyết',
                        'Nhiễm toan ceton do đái tháo đường',
                        'Cơn gout cấp',
                        'Suy tuyến giáp',
                    ],
                    'correct' => 1,
                    'explanation' => 'Tam chứng tăng đường huyết, ceton và hơi thở mùi trái cây rất điển hình cho DKA.',
                    'difficulty' => Difficulty::Hard,
                ],
                [
                    'stem' => 'Bệnh nhân đột ngột yếu nửa người phải, méo miệng và nói khó, khởi phát trong lúc đang làm việc. Chẩn đoán ưu tiên là gì?',
                    'options' => [
                        'Đột quỵ thiếu máu não',
                        'Động kinh vắng ý thức',
                        'Migraine đơn thuần',
                        'Hạ canxi máu',
                    ],
                    'correct' => 0,
                    'explanation' => 'Khởi phát đột ngột thiếu sót thần kinh khu trú là đột quỵ cho đến khi chứng minh ngược lại.',
                    'difficulty' => Difficulty::Medium,
                ],
                [
                    'stem' => 'Người bệnh có sốt, cứng gáy, đau đầu dữ dội và rối loạn tri giác. Chẩn đoán khẩn cấp nào cần nghĩ tới trước tiên?',
                    'options' => [
                        'Viêm màng não',
                        'Rối loạn lo âu',
                        'Viêm dạ dày cấp',
                        'Thiếu máu thiếu sắt',
                    ],
                    'correct' => 0,
                    'explanation' => 'Sốt, cứng gáy và rối loạn tri giác là bộ dấu hiệu cảnh báo viêm màng não.',
                    'difficulty' => Difficulty::Medium,
                ],
            ];

            $examKeys = array_keys(TargetExams::selectable());

            foreach ($questions as $index => $data) {
                $question = Question::query()->updateOrCreate(
                    ['stem' => $data['stem']],
                    [
                        'explanation' => $data['explanation'],
                        'difficulty' => $data['difficulty'],
                        'status' => QuestionStatus::Published,
                        'topic_id' => $topic->getKey(),
                        'is_free' => true,
                    ],
                );

                QuestionOption::query()->where('question_id', $question->getKey())->delete();
                QuestionScope::query()->where('question_id', $question->getKey())->delete();

                foreach ($data['options'] as $optionIndex => $content) {
                    QuestionOption::query()->create([
                        'question_id' => $question->getKey(),
                        'label' => chr(ord('A') + $optionIndex),
                        'content' => $content,
                        'is_correct' => $optionIndex === $data['correct'],
                        'explanation' => $optionIndex === $data['correct']
                            ? $data['explanation']
                            : null,
                        'order' => $optionIndex,
                    ]);
                }

                foreach ($examKeys as $examKey) {
                    QuestionScope::query()->updateOrCreate([
                        'question_id' => $question->getKey(),
                        'scope_type' => QuestionScopeType::Exam,
                        'scope_key' => $examKey,
                    ]);
                }
            }
        });
    }
}
