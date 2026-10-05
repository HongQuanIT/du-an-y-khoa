<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\Models\Profession;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;

/** Publish every available question and assign every active learner audience. */
final class PublishedQuestionAudienceSeeder extends Seeder
{
    public function run(): void
    {
        $professionIds = Profession::query()
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if ($professionIds === []) {
            $this->command?->warn('PublishedQuestionAudienceSeeder: không có đối tượng hoạt động — bỏ qua.');

            return;
        }

        Question::query()
            ->select(['id', 'status', 'version', 'published_version'])
            ->chunkById(100, function ($questions) use ($professionIds): void {
                foreach ($questions as $question) {
                    $version = max(1, (int) $question->version);
                    if ($question->status !== QuestionStatus::Published || (int) $question->published_version !== $version) {
                        $question->forceFill([
                            'status' => QuestionStatus::Published,
                            'published_version' => $version,
                        ])->save();
                    }

                    $question->professions()->sync($professionIds);
                }
            });
    }
}
