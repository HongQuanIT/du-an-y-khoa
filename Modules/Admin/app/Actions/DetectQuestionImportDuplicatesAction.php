<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Collection;
use Modules\QuestionBank\Enums\DuplicateSeverity;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Services\QuestionContentFingerprint;
use Modules\QuestionBank\Services\QuestionSimilarityScorer;

/**
 * Import dedup (SRS §5.8): block exact copies, warn on near-duplicates.
 */
final class DetectQuestionImportDuplicatesAction
{
    use AsAction;

    public function __construct(
        private readonly QuestionContentFingerprint $fingerprint,
        private readonly QuestionSimilarityScorer $scorer,
    ) {}

    /**
     * @param  list<array{line: int, ok: bool, action: ?string, errors: list<string>, values: array<string, string>, payload?: array<string, mixed>, warnings?: list<string>}>  $rows
     * @return list<array{line: int, ok: bool, action: ?string, errors: list<string>, values: array<string, string>, payload?: array<string, mixed>, warnings?: list<string>}>
     */
    public function handle(array $rows): array
    {
        $bank = $this->bankIndex();
        $seenInFile = [];

        foreach ($rows as $index => $row) {
            if (! ($row['ok'] ?? false) || ! isset($row['payload']) || ! is_array($row['payload'])) {
                continue;
            }

            $payload = $row['payload'];
            $stem = (string) ($payload['stem'] ?? '');
            $options = is_array($payload['options'] ?? null) ? $payload['options'] : [];
            $hash = $this->fingerprint->fingerprintFromParts($stem, $options);
            $excludeId = is_string($payload['existing_id'] ?? null) ? (string) $payload['existing_id'] : '';

            if (isset($seenInFile[$hash])) {
                $rows[$index]['ok'] = false;
                $rows[$index]['errors'][] = 'Trùng khớp 100% với dòng '.$seenInFile[$hash].' trong tệp — bỏ dòng này để tránh tạo bản sao.';
                unset($rows[$index]['payload']);

                continue;
            }

            $seenInFile[$hash] = $row['line'];

            $exact = $bank['by_hash']->get($hash);
            if ($exact instanceof Question && (string) $exact->getKey() !== $excludeId) {
                $rows[$index]['ok'] = false;
                $rows[$index]['errors'][] = 'Trùng khớp 100% với câu '.($exact->code ?: $exact->getKey()).' — không tạo bản sao. Điền mã câu đó nếu muốn cập nhật.';
                unset($rows[$index]['payload']);

                continue;
            }

            $warning = $this->nearDuplicateWarning($stem, $options, $hash, $excludeId, $bank);
            if ($warning !== null) {
                $rows[$index]['warnings'] = array_values(array_merge($row['warnings'] ?? [], [$warning]));
            }
        }

        return $rows;
    }

    /**
     * @return array{by_hash: Collection<string, Question>, by_bucket: Collection<string, Collection<int, Question>>}
     */
    private function bankIndex(): array
    {
        $questions = Question::query()
            ->with(['options:id,question_id,content,is_correct'])
            ->get(['id', 'code', 'stem', 'content_fingerprint']);

        $byHash = collect();
        $byBucket = collect();

        foreach ($questions as $question) {
            $hash = $question->content_fingerprint ?: $this->fingerprint->fingerprint($question);
            if ($hash !== '') {
                $byHash[$hash] = $question;
            }

            $bucket = mb_substr($this->fingerprint->stemBucket((string) $question->stem), 0, 24);
            if ($bucket === '') {
                continue;
            }

            $group = $byBucket->get($bucket, collect());
            $group->push($question);
            $byBucket[$bucket] = $group;
        }

        return [
            'by_hash' => $byHash,
            'by_bucket' => $byBucket,
        ];
    }

    /**
     * @param  list<array{content?: string, is_correct?: bool}>  $options
     * @param  array{by_hash: Collection<string, Question>, by_bucket: Collection<string, Collection<int, Question>>}  $bank
     */
    private function nearDuplicateWarning(
        string $stem,
        array $options,
        string $hash,
        string $excludeId,
        array $bank,
    ): ?string {
        $bucket = mb_substr($this->fingerprint->stemBucket($stem), 0, 24);
        if ($bucket === '') {
            return null;
        }

        $candidates = $bank['by_bucket']->get($bucket, collect())
            ->reject(fn (Question $question): bool => (string) $question->getKey() === $excludeId)
            ->take(25);

        $best = null;
        foreach ($candidates as $candidate) {
            $result = $this->scorer->scoreFromParts(
                $stem,
                $options,
                (string) $candidate->stem,
                $candidate->options->all(),
                $hash,
                $candidate->content_fingerprint,
            );

            if ($result['severity'] === null || $result['severity'] === DuplicateSeverity::Exact) {
                continue;
            }

            if ($result['percent'] < DuplicateSeverity::High->minPercent()) {
                continue;
            }

            if ($best === null || $result['percent'] > $best['percent']) {
                $best = [
                    'percent' => $result['percent'],
                    'severity' => $result['severity'],
                    'code' => $candidate->code ?: $candidate->getKey(),
                ];
            }
        }

        if ($best === null) {
            return null;
        }

        return sprintf(
            'Gần trùng %s%% với câu %s (%s) — vẫn import được, hãy rà trước khi gửi duyệt.',
            number_format((float) $best['percent'], 0),
            $best['code'],
            $best['severity']->label(),
        );
    }
}
