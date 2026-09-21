<?php

declare(strict_types=1);

namespace Modules\Exam\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Exam\Enums\ExamStatus;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamTopic;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Support\BlueprintExamAllocator;

/**
 * Learner creates a personal exam paper (bài thi) from a blueprint matrix (kỳ thi).
 */
final class CreateLearnerExamFromBlueprintAction
{
    public function __construct(
        private readonly BlueprintExamAllocator $allocator,
        private readonly GenerateExamQuestionsAction $generateQuestions,
    ) {}

    public function handle(User $user, Blueprint $blueprint): Exam
    {
        $matrix = $this->allocator->allocate($blueprint);

        if (! $matrix['ready']) {
            throw ValidationException::withMessages([
                'blueprint' => $matrix['reason'] ?? 'Ma trận kỳ thi chưa sẵn sàng để tạo bài thi.',
            ]);
        }

        return DB::transaction(function () use ($user, $blueprint, $matrix): Exam {
            $exam = Exam::query()->create([
                'user_id' => $user->id,
                'blueprint_id' => $blueprint->id,
                'title' => $blueprint->name,
                'description' => $blueprint->description,
                'duration_minutes' => $matrix['suggested_duration_minutes'],
                'status' => ExamStatus::Published,
                'is_published' => true,
            ]);

            $sortOrder = 0;
            foreach ($matrix['sections'] as $section) {
                foreach ($section['topics'] as $topic) {
                    $count = (int) ($topic['question_count'] ?? 0);
                    if ($count <= 0) {
                        continue;
                    }

                    ExamTopic::query()->create([
                        'exam_id' => $exam->id,
                        'core_clinical_topic_id' => $topic['id'],
                        'question_count' => $count,
                        'difficulty_counts' => null,
                        'sort_order' => $sortOrder++,
                    ]);
                }
            }

            if ($exam->examTopics()->count() === 0) {
                throw ValidationException::withMessages([
                    'blueprint' => 'Ma trận không phân bổ được chủ đề nào có số câu > 0.',
                ]);
            }

            $result = $this->generateQuestions->handle($exam->fresh(['examTopics.coreClinicalTopic']));
            if ($result['errors'] !== []) {
                throw ValidationException::withMessages([
                    'blueprint' => implode(' ', $result['errors']),
                ]);
            }

            if ($result['synced'] === 0) {
                throw ValidationException::withMessages([
                    'blueprint' => 'Không tạo được câu hỏi cho bài thi từ ngân hàng câu hỏi.',
                ]);
            }

            return $exam->fresh(['questions', 'blueprint', 'examTopics']);
        });
    }
}
