<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Question;

/**
 * Idempotent STEMI demo question with full taxonomy / tags / hints linkage.
 */
final class QuestionDemoSeeder extends Seeder
{
    public const CODE = 'CARDIO-STEMI-001';

    public function run(): void
    {
        $coreTopicId = DB::table('core_clinical_topics')->where('slug', 'dau-nguc')->value('id');
        $taxonomyId = DB::table('medical_taxonomies')
            ->where('code', MedicalKnowledgeTaxonomySeeder::TAXONOMY_CODE)
            ->value('id');

        if ($coreTopicId === null || $taxonomyId === null) {
            return;
        }

        $question = $this->upsertQuestion();
        $this->syncOptions($question);
        $this->syncHints($question);
        $this->syncRelations($question, (int) $coreTopicId, (int) $taxonomyId);
    }

    private function upsertQuestion(): Question
    {
        $existing = Question::withTrashed()->where('code', self::CODE)->first();

        $payload = [
            'stem' => '<p>Nam 62 tuổi, có tiền sử hút thuốc lá và tăng huyết áp, xuất hiện đau ngực sau xương ức dữ dội kéo dài 60 phút, lan ra tay trái, kèm khó thở và vã mồ hôi. ECG cho thấy ST chênh lên ở các chuyển đạo V1–V4. Troponin I tăng cao. Chẩn đoán phù hợp nhất là gì?</p>',
            'explanation' => '<p>Triệu chứng đau ngực kéo dài, ST chênh lên ở V1–V4 và troponin I tăng cao phù hợp với nhồi máu cơ tim cấp có ST chênh lên (STEMI), nhiều khả năng vùng trước. Đây là tình trạng cần tái tưới máu khẩn cấp.</p>',
            'key_info' => [
                'Hãy xác định vị trí tổn thương dựa trên các chuyển đạo ECG.',
                'ST chênh lên ở V1–V4 gợi ý vùng nào của cơ tim bị tổn thương?',
            ],
            'attending_tip' => '<p>STEMI trước (V1–V4) cần PCI cấp cứu khi có chỉ định.</p>',
            'difficulty' => Difficulty::Hard,
            'status' => QuestionStatus::Published,
            'is_free' => true,
            'exam_flag' => true,
        ];

        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->fill($payload)->save();

            return $existing->fresh();
        }

        $question = new Question($payload);
        $question->code = self::CODE;
        $question->version = 1;
        $question->save();

        return $question->fresh();
    }

    private function syncOptions(Question $question): void
    {
        $options = [
            ['label' => 'A', 'content' => 'Viêm màng ngoài tim cấp', 'is_correct' => false, 'order' => 1],
            ['label' => 'B', 'content' => 'Nhồi máu cơ tim cấp có ST chênh lên (STEMI)', 'is_correct' => true, 'order' => 2],
            ['label' => 'C', 'content' => 'Thuyên tắc phổi', 'is_correct' => false, 'order' => 3],
            ['label' => 'D', 'content' => 'Bóc tách động mạch chủ', 'is_correct' => false, 'order' => 4],
            ['label' => 'E', 'content' => 'Đau thắt ngực ổn định', 'is_correct' => false, 'order' => 5],
        ];

        $keepIds = [];

        foreach ($options as $row) {
            $option = $question->options()->updateOrCreate(
                ['label' => $row['label']],
                [
                    'content' => $row['content'],
                    'is_correct' => $row['is_correct'],
                    'explanation' => $row['is_correct']
                        ? 'STEMI: đau ngực kéo dài + ST chênh lên + troponin tăng.'
                        : null,
                    'order' => $row['order'],
                ],
            );
            $keepIds[] = $option->id;
        }

        $question->options()->whereNotIn('id', $keepIds)->delete();
    }

    private function syncHints(Question $question): void
    {
        $hints = [
            ['content' => 'Hãy xác định vị trí tổn thương dựa trên các chuyển đạo ECG.', 'sort_order' => 1],
            ['content' => 'ST chênh lên ở V1–V4 gợi ý vùng nào của cơ tim bị tổn thương?', 'sort_order' => 2],
        ];

        $keepIds = [];

        foreach ($hints as $hint) {
            $row = $question->hints()->updateOrCreate(
                ['sort_order' => $hint['sort_order']],
                [
                    'content' => $hint['content'],
                    'status' => TaxonomyStatus::Active,
                ],
            );
            $keepIds[] = $row->id;
        }

        $question->hints()->whereNotIn('id', $keepIds)->delete();
    }

    private function syncRelations(Question $question, int $coreTopicId, int $taxonomyId): void
    {
        $question->coreClinicalTopics()->sync([$coreTopicId]);

        $nodeLinks = [
            'tim-mach' => ['relationship_type' => 'contextual', 'is_primary' => false],
            'benh-dong-mach-vanh' => ['relationship_type' => 'related', 'is_primary' => false],
            'hoi-chung-vanh-cap' => ['relationship_type' => 'related', 'is_primary' => false],
            'stemi' => ['relationship_type' => 'primary', 'is_primary' => true],
            'symptom-dau-nguc' => ['relationship_type' => 'related', 'is_primary' => false],
            'symptom-kho-tho' => ['relationship_type' => 'related', 'is_primary' => false],
            'symptom-va-mo-hoi' => ['relationship_type' => 'related', 'is_primary' => false],
            'finding-st-chenh-len' => ['relationship_type' => 'related', 'is_primary' => false],
            'finding-troponin-i-tang' => ['relationship_type' => 'related', 'is_primary' => false],
            'concept-nhan-dien-stemi' => ['relationship_type' => 'tested', 'is_primary' => false],
            'concept-dinh-khu-mi-ecg' => ['relationship_type' => 'tested', 'is_primary' => false],
            'concept-troponin-mi' => ['relationship_type' => 'tested', 'is_primary' => false],
            'concept-tai-tuoi-mau-stemi' => ['relationship_type' => 'tested', 'is_primary' => false],
        ];

        $sync = [];
        foreach ($nodeLinks as $slug => $pivot) {
            $nodeId = DB::table('medical_taxonomy_nodes')
                ->where('medical_taxonomy_id', $taxonomyId)
                ->where('slug', $slug)
                ->value('id');

            if ($nodeId === null) {
                continue;
            }

            $sync[(int) $nodeId] = $pivot;
        }

        $question->medicalTaxonomyNodes()->sync($sync);

        $tagIds = DB::table('tags')
            ->whereIn('slug', ['ecg', 'cardiology', 'emergency', 'diagnosis', 'high-yield', 'adult'])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $question->tags()->sync($tagIds);
    }
}
