<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class QuestionPendingPublishSeeder extends Seeder
{
    public function run(): void
    {
        $editor = \App\Models\User::where('email', 'editor@medlearn.local')->first();
        $instructor1 = \App\Models\User::where('email', 'instructor@medlearn.local')->first();
        $instructor2 = \App\Models\User::where('email', 'instructor2@medlearn.local')->first();

        if (!$editor || !$instructor1 || !$instructor2) {
            $this->command->error('Missing users. Please run UserSeeder first.');
            return;
        }

        $question = \Modules\QuestionBank\Models\Question::factory()->create([
            'status' => \Modules\QuestionBank\Enums\QuestionStatus::PendingPublish,
            'created_by' => $editor->id,
            'assigned_instructor_id' => $instructor1->id,
            'reviewer_1_id' => $instructor1->id,
            'reviewer_2_id' => $instructor2->id,
            'stem' => 'Bệnh nhân nam 45 tuổi nhập viện vì đau ngực trái lan ra sau lưng. Dấu hiệu sinh tồn ổn định. Điện tâm đồ bình thường. Chẩn đoán nào sau đây là phù hợp nhất?',
            'code' => 'Q-SEED-01',
        ]);

        \Modules\QuestionBank\Models\QuestionReviewRequest::create([
            'question_id' => $question->id,
            'requester_id' => $editor->id,
            'reviewer_id' => $instructor1->id,
            'action' => \Modules\QuestionBank\Enums\QuestionReviewAction::Create,
            'status' => 'approved',
            'review_note' => 'Đồng ý',
        ]);

        \Modules\QuestionBank\Models\QuestionReviewRequest::create([
            'question_id' => $question->id,
            'requester_id' => $editor->id,
            'reviewer_id' => $instructor2->id,
            'action' => \Modules\QuestionBank\Enums\QuestionReviewAction::Create,
            'status' => 'approved',
            'review_note' => 'Nội dung chuẩn',
        ]);

        $this->command->info('Seeded a PendingPublish question for editor@medlearn.local approved by both instructors.');
    }
}
