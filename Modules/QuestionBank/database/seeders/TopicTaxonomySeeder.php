<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\QuestionBank\Models\Topic;
use RuntimeException;

/**
 * Build topic taxonomy from the downloaded VM14K dataset.
 */
final class TopicTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $files = $this->datasetFiles();
        $this->seedFromVm14k($files);
    }

    /**
     * @return list<string>
     */
    private function datasetFiles(): array
    {
        $dir = base_path('Modules/QuestionBank/database/seeders/data/vm14k');
        $files = glob($dir.'/data-processed-shuffled*.jsonl') ?: [];

        if ($files === []) {
            throw new RuntimeException(
                'VM14K dataset files not found. Download them into Modules/QuestionBank/database/seeders/data/vm14k first.'
            );
        }

        sort($files);

        return array_values($files);
    }

    /** @param list<string> $files */
    private function seedFromVm14k(array $files): void
    {
        $specialtyOrder = 0;
        $systemOrder = 0;
        $specialties = [];
        $systems = [];

        foreach ($files as $file) {
            $handle = fopen($file, 'rb');
            if ($handle === false) {
                continue;
            }

            while (($line = fgets($handle)) !== false) {
                $row = json_decode(trim($line), true);
                if (! is_array($row)) {
                    continue;
                }

                $medicalTopic = $row['medical_topic'] ?? null;
                if (! is_array($medicalTopic) || ($medicalTopic[0] ?? null) === null) {
                    continue;
                }

                $specialtyName = trim((string) $medicalTopic[0]);
                $specialtySlug = $this->topicSlug($specialtyName);
                if ($specialtyName === '' || $specialtySlug === '') {
                    continue;
                }

                if (! isset($specialties[$specialtySlug])) {
                    $specialties[$specialtySlug] = Topic::updateOrCreate(
                        ['slug' => $specialtySlug],
                        [
                            'name' => $specialtyName,
                            'type' => 'specialty',
                            'order' => $specialtyOrder++,
                            'parent_id' => null,
                        ],
                    );
                }

                $systemName = trim((string) ($medicalTopic[1] ?? ''));
                $systemSlug = $this->topicSlug($systemName);
                if ($systemName === '' || $systemSlug === '') {
                    continue;
                }

                $systemKey = $specialtySlug.'|'.$systemSlug;
                if (! isset($systems[$systemKey])) {
                    $systems[$systemKey] = Topic::updateOrCreate(
                        ['slug' => $systemSlug],
                        [
                            'name' => $systemName,
                            'type' => 'system',
                            'order' => $systemOrder++,
                            'parent_id' => $specialties[$specialtySlug]->id,
                        ],
                    );
                }
            }

            fclose($handle);
        }
    }

    private function topicSlug(string $name): string
    {
        return Str::limit((string) Str::slug($name), 191, '');
    }
}
