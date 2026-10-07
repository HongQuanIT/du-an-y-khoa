<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Services;

use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\OrganSystem;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\Subject;

/**
 * Turns storage/logs/adaptive.log into a short business narrative.
 */
final class AdaptiveSessionBriefing
{
    /**
     * @param  array<int|string, string>  $learners
     * @param  array<int|string, string>  $blueprints
     * @param  array<int|string, string>  $questions
     * @param  array<int|string, string>  $organs
     * @param  array<int|string, string>  $subjects
     */
    public function __construct(
        private array $learners = [],
        private array $blueprints = [],
        private array $questions = [],
        private array $organs = [],
        private array $subjects = [],
    ) {}

    /**
     * Newest sessions first.
     *
     * @return list<array<string, mixed>>
     */
    public function latest(int $limit = 12): array
    {
        $path = (string) config('logging.channels.adaptive.path');
        if ($path === '' || ! is_file($path)) {
            return [];
        }

        $runs = array_slice(array_reverse($this->runs($this->tail($path))), 0, $limit);
        $this->loadLabels($runs);

        return array_map(fn (array $run): array => $this->brief($run), $runs);
    }

    /**
     * @return array{sessions: list<array<string, mixed>>}
     */
    public function page(int $sessionLimit = 12): array
    {
        $path = (string) config('logging.channels.adaptive.path');
        if ($path === '' || ! is_file($path)) {
            return ['sessions' => [], 'rows' => []];
        }

        $runs = $this->runs($this->tail($path));
        $this->loadLabels($runs);

        return [
            'sessions' => array_map(
                fn (array $run): array => $this->brief($run),
                array_slice(array_reverse($runs), 0, $sessionLimit),
            ),
        ];
    }

    /**
     * Questions taken from the log, highest pick count, then wrong, then correct.
     *
     * @param  list<array{at: string, steps: array<string, array{at: string, step: string, context: array<string, mixed>}>}>  $runs
     * @return list<array<string, string>>
     */
    public function sheet(array $runs): array
    {
        /** @var array<string, array<string, mixed>> $rows */
        $rows = [];

        foreach ($runs as $run) {
            $context = $run['steps']['path']['context']
                ?? $run['steps']['start']['context']
                ?? $run['steps']['served']['context']
                ?? [];
            $learnerId = $context['user_id'] ?? null;
            $learner = $this->label($this->learners, $learnerId, 'Học viên');

            foreach ($this->selectedEntries($run) as $entry) {
                $key = (string) $learnerId.'|'.$entry['question_id'];
                if (! isset($rows[$key])) {
                    $rows[$key] = [
                        'learner' => $learner,
                        'code' => $this->label($this->questions, $entry['question_id'], 'Một câu'),
                        'picked' => 0,
                        'wrong' => 0,
                        'correct' => 0,
                        'weakness' => null,
                        'days' => null,
                        'memory' => null,
                        'cooldown' => null,
                        'weight' => null,
                        'role' => '',
                    ];
                }

                $rows[$key]['picked']++;
                if ($entry['scored']) {
                    $rows[$key]['wrong'] = $entry['wrong'];
                    $rows[$key]['correct'] = $entry['correct'];
                    $rows[$key]['weakness'] = $entry['weakness'];
                    $rows[$key]['days'] = $entry['days'];
                    $rows[$key]['memory'] = $entry['memory'];
                    $rows[$key]['cooldown'] = $entry['cooldown'];
                    $rows[$key]['weight'] = $entry['weight'];
                    $rows[$key]['role'] = 'Ôn lại';
                } elseif ($rows[$key]['role'] === '') {
                    $rows[$key]['role'] = 'Chưa chấm';
                }
            }
        }

        $list = array_values($rows);
        usort($list, static function (array $left, array $right): int {
            return [$right['picked'], $right['wrong'], $right['correct'], $left['code']]
                <=> [$left['picked'], $left['wrong'], $left['correct'], $right['code']];
        });

        $display = [];
        foreach ($list as $index => $row) {
            $display[] = [
                'rank' => (string) ($index + 1),
                'learner' => (string) $row['learner'],
                'code' => (string) $row['code'],
                'picked' => (string) $row['picked'],
                'wrong' => (string) $row['wrong'],
                'correct' => (string) $row['correct'],
                'weakness' => $row['weakness'] === null ? '—' : ((int) round(((float) $row['weakness']) * 100)).'%',
                'days' => $row['days'] === null ? '—' : (string) (int) round((float) $row['days']),
                'memory' => $this->decimal($row['memory']),
                'hold' => $this->holdLabel($row['cooldown']),
                'priority' => $this->decimal($row['weight']),
                'role' => (string) $row['role'],
            ];
        }

        return $display;
    }

    public function logExists(): bool
    {
        $path = (string) config('logging.channels.adaptive.path');

        return $path !== '' && is_file($path);
    }

    /**
     * @return list<array{at: string, steps: array<string, array{at: string, step: string, context: array<string, mixed>}>}>
     */
    public function runs(string $log): array
    {
        /** @var list<array{at: string, steps: array<string, array{at: string, step: string, context: array<string, mixed>}>}> $runs */
        $runs = [];
        /** @var array<string, int> $indexByTrace */
        $indexByTrace = [];
        $open = null;

        foreach (preg_split("/\r\n|\n|\r/", $log) ?: [] as $line) {
            $event = $this->parseLine((string) $line);
            if ($event === null) {
                continue;
            }

            $trace = isset($event['context']['trace_id']) ? (string) $event['context']['trace_id'] : '';
            if ($trace !== '') {
                if (! isset($indexByTrace[$trace])) {
                    $indexByTrace[$trace] = count($runs);
                    $runs[] = ['at' => $event['at'], 'steps' => []];
                }
                $runs[$indexByTrace[$trace]]['steps'][$event['step']] = $event;
                $open = null;

                continue;
            }

            if ($open === null || in_array($event['step'], ['path', 'start'], true)) {
                $open = count($runs);
                $runs[] = ['at' => $event['at'], 'steps' => []];
            }

            $runs[$open]['steps'][$event['step']] = $event;
        }

        return $runs;
    }

    /**
     * @param  array{at: string, steps: array<string, array{at: string, step: string, context: array<string, mixed>}>}  $run
     * @return array<string, mixed>
     */
    public function brief(array $run): array
    {
        $steps = $run['steps'];
        $path = $steps['path']['context'] ?? [];
        $start = $steps['start']['context'] ?? [];
        $pipeline = (string) ($start['pipeline'] ?? $steps['result']['context']['pipeline'] ?? '');

        if ($pipeline === 'filter_group_quota_v2' || isset($steps['filter'], $steps['group'], $steps['quota'])) {
            return $this->briefV2($run);
        }

        $pool = $steps['pool']['context'] ?? [];
        $split = $steps['coverage_split']['context'] ?? [];
        $scores = $steps['review_scores']['context'] ?? [];
        $sampled = $steps['review_sampled']['context'] ?? [];
        $result = $steps['result']['context'] ?? [];
        $served = $steps['served']['context'] ?? [];
        $topUp = $steps['top_up']['context'] ?? null;

        $focus = (string) ($start['focus'] ?? $path['focus'] ?? 'balanced');
        $learnerId = $start['user_id'] ?? $path['user_id'] ?? $served['user_id'] ?? null;
        $blueprintId = $start['blueprint_id'] ?? $path['blueprint_id'] ?? $pool['blueprint_id'] ?? null;
        $learner = $this->label($this->learners, $learnerId, 'Học viên');
        $blueprint = $this->label($this->blueprints, $blueprintId, 'đề đã chọn');
        $focusMeta = $this->focus($focus);
        $isLegacy = ($path['path'] ?? '') === 'legacy_incorrect_first';
        $empty = isset($steps['empty_pool']) || (int) ($pool['pool_size'] ?? -1) === 0;

        $picked = (int) ($result['picked_count'] ?? $served['count'] ?? 0);
        $poolSize = (int) ($split['pool_size'] ?? $pool['pool_size'] ?? 0);
        [$newCount, $reviewCount] = $this->selectedCounts($result, $split);

        return [
            'when' => $this->when($run['at']),
            'learner' => $learner,
            'focus' => $focusMeta['label'],
            'focus_tone' => $focusMeta['tone'],
            'pipeline' => 'v1',
            'pipeline_label' => 'Điểm ưu tiên (cũ)',
            'headline' => $this->headline($isLegacy, $empty, $learner, $focusMeta['label'], $blueprint, $picked, $poolSize),
            'summary' => 'Log thuật toán cũ (điểm ưu tiên). '.$this->summary($isLegacy, $empty, $focusMeta['intent'], $start, $pool, $split, $topUp),
            'stages' => [],
            'stats' => $empty ? [] : [
                ['label' => 'Câu được chọn', 'value' => (string) max($picked, $newCount + $reviewCount)],
                ['label' => 'Câu chưa chấm', 'value' => (string) $newCount],
                ['label' => 'Câu ôn lại', 'value' => (string) $reviewCount],
                ['label' => 'Câu trong đề', 'value' => (string) $poolSize],
            ],
            'reasons' => $isLegacy || $empty ? [] : $this->reasons($sampled, $scores),
            'fresh' => $isLegacy || $empty ? [] : $this->freshQuestions($result, $sampled),
            'table' => $isLegacy || $empty ? [] : $this->sessionTable($result, $scores, $sampled),
            'resting' => [],
            'graded' => [],
            'formulas' => $isLegacy || $empty ? [] : $this->formulas($focusMeta, [
                ...$scores,
                'w_weakness' => $scores['w_weakness'] ?? $start['w_weakness'] ?? null,
                'w_memory' => $scores['w_memory'] ?? $start['w_memory'] ?? null,
            ], $split),
            'closing' => $this->closing($isLegacy, $empty, isset($steps['served'])),
        ];
    }

    /**
     * @param  array{at: string, steps: array<string, array{at: string, step: string, context: array<string, mixed>}>}  $run
     * @return array<string, mixed>
     */
    private function briefV2(array $run): array
    {
        $steps = $run['steps'];
        $start = $steps['start']['context'] ?? [];
        $pool = $steps['pool']['context'] ?? [];
        $filter = $steps['filter']['context'] ?? [];
        $group = $steps['group']['context'] ?? [];
        $quota = $steps['quota']['context'] ?? [];
        $result = $steps['result']['context'] ?? [];
        $served = $steps['served']['context'] ?? [];

        $focus = (string) ($start['focus'] ?? 'balanced');
        $learnerId = $start['user_id'] ?? $served['user_id'] ?? null;
        $blueprintId = $start['blueprint_id'] ?? $pool['blueprint_id'] ?? null;
        $learner = $this->label($this->learners, $learnerId, 'Học viên');
        $blueprint = $this->label($this->blueprints, $blueprintId, 'đề đã chọn');
        $focusMeta = $this->focus($focus);
        $empty = isset($steps['empty_pool']) || (int) ($pool['pool_size'] ?? -1) === 0;

        $picked = (int) ($result['picked_count'] ?? $served['count'] ?? 0);
        $poolSize = (int) ($pool['pool_size'] ?? 0);
        $buckets = (array) ($result['bucket_counts'] ?? []);
        $band = (string) ($quota['due_band'] ?? 'low');
        $sessionSize = (int) ($start['limit'] ?? $picked);
        if ($sessionSize <= 0) {
            $sessionSize = max(1, (int) ($quota['new_count'] ?? 0) + (int) ($quota['review_slots'] ?? 0));
        }
        $duePoolSize = (int) ($group['due_pool'] ?? 0);
        $bandLabel = $this->dueBandLabel($band, $duePoolSize, $sessionSize);

        $excluded = (array) ($filter['excluded'] ?? []);
        $stages = $empty ? [] : $this->pipelineStagesV2($filter, $group, $quota, $focusMeta, $bandLabel, $buckets);

        $summary = $empty
            ? 'Không còn câu phù hợp trong phạm vi đề.'
            : sprintf(
                '%s chọn hướng %s trên %s theo pipeline Lọc → Phân nhóm → Phân suất. ① Lọc: còn %d câu ôn + %d câu mới (loại thrash %d · cooldown %d · version %d · làm nhanh chưa S/W %d). ② Phân nhóm: %d yếu · %d sắp quên · %d mới. ③ Phân suất: %s → %s câu mới / %s suất ôn (yếu %s · due %s). Kết quả chọn: %d yếu · %d sắp quên · %d mới · %d lấp.',
                $learner,
                $focusMeta['label'],
                $blueprint,
                (int) ($filter['eligible_count'] ?? 0),
                (int) ($filter['unseen_count'] ?? 0),
                (int) ($excluded['thrash'] ?? 0),
                (int) ($excluded['cooldown'] ?? 0),
                (int) ($excluded['version_mismatch'] ?? 0),
                (int) ($excluded['ungraded_status'] ?? 0),
                (int) ($group['weak_pool'] ?? 0),
                (int) ($group['due_pool'] ?? 0),
                (int) ($group['unseen_count'] ?? $filter['unseen_count'] ?? 0),
                $bandLabel,
                (string) ($quota['new_count'] ?? 0),
                (string) ($quota['review_slots'] ?? 0),
                (string) ($quota['weak_quota'] ?? 0),
                (string) ($quota['due_quota'] ?? 0),
                (int) ($buckets['yeu'] ?? 0),
                (int) ($buckets['sap_quen'] ?? 0),
                (int) ($buckets['moi'] ?? 0),
                (int) ($buckets['lap_day'] ?? 0),
            );

        if ((int) ($result['shortfall'] ?? 0) > 0) {
            $summary .= ' '.((string) ($result['message'] ?? 'Thiếu câu sau phân suất.'));
        }

        return [
            'when' => $this->when($run['at']),
            'learner' => $learner,
            'focus' => $focusMeta['label'],
            'focus_tone' => $focusMeta['tone'],
            'pipeline' => 'v2',
            'pipeline_label' => 'Lọc → Phân nhóm → Phân suất',
            'headline' => $this->headline(false, $empty, $learner, $focusMeta['label'], $blueprint, $picked, $poolSize),
            'summary' => $summary,
            'stages' => $stages,
            'stats' => $empty ? [] : [
                ['label' => 'Câu được chọn', 'value' => (string) $picked],
                ['label' => 'Yếu / Sắp quên', 'value' => ((int) ($buckets['yeu'] ?? 0)).' / '.((int) ($buckets['sap_quen'] ?? 0))],
                ['label' => 'Mới / Lấp', 'value' => ((int) ($buckets['moi'] ?? 0)).' / '.((int) ($buckets['lap_day'] ?? 0))],
                ['label' => 'Tồn đọng due', 'value' => $bandLabel],
            ],
            'reasons' => [],
            'fresh' => [],
            'table' => $empty ? [] : $this->sessionTableV2($result),
            'resting' => $empty ? [] : $this->restingTableV2($filter),
            'graded' => $this->gradedTableV2($steps['graded']['context'] ?? []),
            'formulas' => $empty ? [] : $this->formulasV2($focusMeta, $quota, $group, $filter),
            'closing' => $this->closingV2($empty, isset($steps['served']), isset($steps['graded'])),
        ];
    }

    /**
     * Ba trụ theo đúng thứ tự thực thi / log: Lọc → Phân nhóm → Phân suất.
     *
     * @param  array<string, mixed>  $filter
     * @param  array<string, mixed>  $group
     * @param  array<string, mixed>  $quota
     * @param  array{label: string, intent: string, tone: string}  $focus
     * @param  array<string, int>  $buckets
     * @return list<array{order: int, name: string, title: string, body: string, items: list<string>}>
     */
    private function pipelineStagesV2(
        array $filter,
        array $group,
        array $quota,
        array $focus,
        string $bandLabel,
        array $buckets,
    ): array {
        $excluded = (array) ($filter['excluded'] ?? []);
        $restingCount = count((array) ($filter['resting'] ?? []));
        $newSharePct = isset($quota['new_share'])
            ? ((int) round(((float) $quota['new_share']) * 100)).'%'
            : '—';

        return [
            [
                'order' => 1,
                'name' => 'Lọc',
                'title' => '① Lọc — câu nào được phép vào phiên',
                'body' => sprintf(
                    'Pool %d → còn %d câu ôn + %d câu mới. Loại: thrash %d · cooldown thích ứng %d · lệch phiên bản %d · làm nhanh chưa S/W %d. Đang nghỉ liệt kê: %d câu.',
                    (int) ($filter['active_count'] ?? 0),
                    (int) ($filter['eligible_count'] ?? 0),
                    (int) ($filter['unseen_count'] ?? 0),
                    (int) ($excluded['thrash'] ?? 0),
                    (int) ($excluded['cooldown'] ?? 0),
                    (int) ($excluded['version_mismatch'] ?? 0),
                    (int) ($excluded['ungraded_status'] ?? 0),
                    $restingCount,
                ),
                'items' => [
                    'Thrash: sai ≥3 → 72h + 2 phiên; ≥5 → tạm không đưa vào phiên 7 ngày',
                    'Cooldown: nghỉ đến ngày học kế tiếp (04:00, tối thiểu 8 giờ) — chỉ sau phiên thích ứng',
                    'Phiên bản nội dung lệch → coi như câu mới',
                    'Đã trả lời dưới 5s: vẫn là đã làm, không vào câu mới, chưa có S/W nên không ôn',
                    'Không gồm tỉ lệ câu mới (đó là bước Phân suất)',
                ],
            ],
            [
                'order' => 2,
                'name' => 'Phân nhóm',
                'title' => '② Phân nhóm — xếp vào giỏ học tập',
                'body' => sprintf(
                    'Yếu %d · Sắp quên %d · Mới %d · chồng yếu∩due %d.',
                    (int) ($group['weak_pool'] ?? 0),
                    (int) ($group['due_pool'] ?? 0),
                    (int) ($group['unseen_count'] ?? $filter['unseen_count'] ?? 0),
                    (int) ($group['overlap_weak_due'] ?? 0),
                ),
                'items' => [
                    'Yếu: W ≥ 50% trên tối đa 5 lần gần nhất',
                    'Sắp quên: đến hạn theo ngày học (study_day + S)',
                    'Mới: chưa trả lời đúng/sai trên phiên bản hiện tại (lượt dưới 5s vẫn là đã làm; omit chưa tính)',
                ],
            ],
            [
                'order' => 3,
                'name' => 'Phân suất',
                'title' => '③ Phân suất — lấy bao nhiêu từ mỗi giỏ',
                'body' => sprintf(
                    'Tồn đọng %s → tỉ lệ câu mới %s (%s câu) · suất ôn %s (yếu %s · sắp quên %s) theo hướng %s. Đã chọn: %d yếu · %d sắp quên · %d mới · %d lấp.',
                    $bandLabel,
                    $newSharePct,
                    (string) ($quota['new_count'] ?? 0),
                    (string) ($quota['review_slots'] ?? 0),
                    (string) ($quota['weak_quota'] ?? 0),
                    (string) ($quota['due_quota'] ?? 0),
                    $focus['label'],
                    (int) ($buckets['yeu'] ?? 0),
                    (int) ($buckets['sap_quen'] ?? 0),
                    (int) ($buckets['moi'] ?? 0),
                    (int) ($buckets['lap_day'] ?? 0),
                ),
                'items' => [
                    'So sánh số câu đến hạn (duePool) với N = số câu phiên',
                    'Due thấp: duePool < 1×N → ~30% câu mới',
                    'Due vừa: 1×N ≤ duePool < 3×N → ~20% câu mới',
                    'Due cao: duePool ≥ 3×N → ~10% câu mới (ưu tiên ôn tồn đọng)',
                    'Học viên mới (chưa có câu đã chấm): 100% câu mới',
                    'Mode chỉ chia suất ôn (Yếu vs Sắp quên), không đổi % câu mới',
                ],
            ],
        ];
    }

    private function dueBandLabel(string $band, int $duePool, int $sessionSize): string
    {
        $n = max(1, $sessionSize);

        return match ($band) {
            'new' => 'Học viên mới → 100% câu mới',
            'high' => sprintf('Due cao: %d ≥ 3×N (N=%d) → 10%% mới', $duePool, $n),
            'mid' => sprintf('Due vừa: %d ∈ [1×N, 3×N) (N=%d) → 20%% mới', $duePool, $n),
            default => sprintf('Due thấp: %d < 1×N (N=%d) → 30%% mới', $duePool, $n),
        };
    }

    /**
     * @param  array<string, mixed>  $result
     * @return list<array<string, string>>
     */
    private function sessionTableV2(array $result): array
    {
        $rows = [];
        foreach ((array) ($result['items'] ?? []) as $item) {
            if (! is_array($item) || ! isset($item['question_id'])) {
                continue;
            }
            $bucket = (string) ($item['bucket'] ?? '');
            $bucketLabel = match ($bucket) {
                'yeu' => 'Yếu',
                'sap_quen' => 'Sắp quên',
                'moi' => 'Mới',
                'lap_day' => 'Lấp',
                default => $bucket !== '' ? $bucket : '—',
            };
            $rows[] = [
                'rank' => (string) ($item['position'] ?? count($rows) + 1),
                'code' => $this->label($this->questions, $item['question_id'], 'Một câu'),
                'bucket' => $bucketLabel,
                'reason' => (string) ($item['reason'] ?? '—'),
                'weakness' => isset($item['weakness']) && $item['weakness'] !== null
                    ? $this->percent((float) $item['weakness'])
                    : '—',
                'days' => isset($item['days_since']) && $item['days_since'] !== null
                    ? $this->decimal($item['days_since'])
                    : '—',
                'retention' => isset($item['retention']) && $item['retention'] !== null
                    ? $this->percent((float) $item['retention'])
                    : '—',
                'stability' => isset($item['stability']) && $item['stability'] !== null
                    ? $this->decimal($item['stability']).' ngày'
                    : '—',
                'due' => $this->dueLabel(
                    isset($item['due_at']) ? (string) $item['due_at'] : null,
                    isset($item['is_due']) ? (bool) $item['is_due'] : null,
                ),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return list<array<string, string>>
     */
    /**
     * @param  array<string, mixed>  $graded
     * @return list<array<string, string>>
     */
    private function gradedTableV2(array $graded): array
    {
        if ($graded === []) {
            return [];
        }

        $rows = [];
        foreach ((array) ($graded['items'] ?? []) as $item) {
            if (! is_array($item) || ! isset($item['question_id'])) {
                continue;
            }
            $result = (string) ($item['result'] ?? '');
            $resultLabel = match ($result) {
                'correct' => 'Đúng',
                'incorrect' => 'Sai',
                'omitted' => 'Bỏ qua',
                default => '—',
            };
            $valid = $item['valid'] ?? null;
            $validLabel = match (true) {
                $valid === true => 'Có',
                $valid === false => 'Không (quá nhanh)',
                default => '—',
            };
            $wasDue = $item['was_due'] ?? null;
            $dueState = match (true) {
                $wasDue === true => 'Đúng hạn',
                $wasDue === false => 'Sớm',
                default => '—',
            };
            $recent = (array) ($item['recent_results'] ?? []);
            $recentLabel = $recent === []
                ? '—'
                : implode('', array_map(static fn ($v): string => $v ? 'Đ' : 'S', $recent));

            $rows[] = [
                'rank' => (string) ($item['position'] ?? count($rows) + 1),
                'code' => $this->label($this->questions, $item['question_id'], 'Một câu'),
                'result' => $resultLabel,
                'valid' => $validLabel,
                's_before' => isset($item['s_before']) && $item['s_before'] !== null
                    ? $this->decimal($item['s_before'])
                    : '—',
                's_after' => isset($item['s_after']) && $item['s_after'] !== null
                    ? $this->decimal($item['s_after'])
                    : '—',
                't_days' => isset($item['t_days']) && $item['t_days'] !== null
                    ? $this->decimal($item['t_days'])
                    : '—',
                'due_state' => $dueState,
                'due' => $this->dueLabel(
                    isset($item['due_at']) ? (string) $item['due_at'] : null,
                    null,
                ),
                'weakness' => isset($item['weakness_after']) && $item['weakness_after'] !== null
                    ? $this->percent((float) $item['weakness_after'])
                    : '—',
                'streak' => isset($item['wrong_streak']) && $item['wrong_streak'] !== null
                    ? (string) (int) $item['wrong_streak']
                    : '—',
                'recent' => $recentLabel,
                'time' => isset($item['time_spent_seconds']) && $item['time_spent_seconds'] !== null
                    ? ((string) (int) $item['time_spent_seconds']).'s'
                    : '—',
                'note' => (string) ($item['note'] ?? '—'),
            ];
        }

        return $rows;
    }

    private function restingTableV2(array $filter): array
    {
        $rows = [];
        foreach ((array) ($filter['resting'] ?? []) as $item) {
            if (! is_array($item) || ! isset($item['question_id'])) {
                continue;
            }
            $reason = (string) ($item['reason'] ?? '');
            $reasonLabel = match ($reason) {
                'thrash' => 'Thrash',
                'cooldown' => 'Nghỉ serve',
                default => $reason !== '' ? $reason : '—',
            };
            $rows[] = [
                'code' => $this->label($this->questions, $item['question_id'], 'Một câu'),
                'reason' => $reasonLabel,
                'detail' => (string) ($item['detail'] ?? '—'),
                'rest_until' => $this->timestampLabel(
                    isset($item['rest_until']) ? (string) $item['rest_until'] : null,
                ),
                'due' => $this->dueLabel(
                    isset($item['due_at']) ? (string) $item['due_at'] : null,
                    isset($item['is_due']) ? (bool) $item['is_due'] : null,
                ),
                'stability' => isset($item['stability']) && $item['stability'] !== null
                    ? $this->decimal($item['stability']).' ngày'
                    : '—',
                'retention' => isset($item['retention']) && $item['retention'] !== null
                    ? $this->percent((float) $item['retention'])
                    : '—',
            ];
        }

        return $rows;
    }

    private function dueLabel(?string $dueAt, ?bool $isDue): string
    {
        if ($dueAt === null || $dueAt === '') {
            return '—';
        }

        $label = $this->timestampLabel($dueAt);
        if ($label === '—') {
            return '—';
        }

        if ($isDue === true) {
            return 'Đã đến hạn ('.$label.')';
        }

        if ($isDue === false) {
            return 'Đến hạn '.$label;
        }

        return $label;
    }

    private function timestampLabel(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        try {
            $parsed = new DateTimeImmutable($value);
        } catch (\Exception) {
            return '—';
        }

        return $parsed->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('H:i, d/m/Y');
    }

    /**
     * @param  array{label: string, intent: string, tone: string}  $focus
     * @param  array<string, mixed>  $quota
     * @param  array<string, mixed>  $group
     * @param  array<string, mixed>  $filter
     * @return list<array{name: string, expr: string}>
     */
    private function formulasV2(array $focus, array $quota, array $group, array $filter): array
    {
        $excluded = (array) ($filter['excluded'] ?? []);
        $restingCount = count((array) ($filter['resting'] ?? []));

        return [
            ['name' => 'Thứ tự pipeline', 'expr' => 'Luôn chạy **① Lọc → ② Phân nhóm → ③ Phân suất**'],
            ['name' => '① Lọc', 'expr' => 'Loại thrash (**sai ≥3 → 72h + 2 phiên**; **≥5 → nghỉ 7 ngày**), nghỉ serve **đến ngày học kế tiếp (04:00, tối thiểu 8 giờ) chỉ sau phiên thích ứng**, lệch phiên bản nội dung, **đã làm nhưng dưới 5s** (không câu mới, chưa S/W). **Không** quyết định tỉ lệ câu mới. Đã loại: thrash **'.((string) ($excluded['thrash'] ?? 0)).'**, cooldown **'.((string) ($excluded['cooldown'] ?? 0)).'**, version **'.((string) ($excluded['version_mismatch'] ?? 0)).'**, làm nhanh **'.((string) ($excluded['ungraded_status'] ?? 0)).'**. Nghỉ liệt kê **'.$restingCount.'** câu.'],
            ['name' => '② Phân nhóm — Yếu', 'expr' => 'Độ yếu = **(sai + 1) / (số lần + 2)** trên tối đa 5 lần gần nhất. Vào nhóm khi **≥ 50%**. Pool: **'.((string) ($group['weak_pool'] ?? '—')).'**.'],
            ['name' => '② Phân nhóm — Sắp quên', 'expr' => 'Đến hạn khi **ngày học ≥ ngày học lần chấm + S**. S ∈ **1 · 3 · 7 · 14 · 30 · 60**; R = **0,9^(t/S)**. Pool: **'.((string) ($group['due_pool'] ?? '—')).'**. Due = **04:00 ngày học đến hạn**.'],
            ['name' => '③ Phân suất — câu mới', 'expr' => 'Gọi **N** = số câu phiên, **duePool** = số câu đến hạn sau lọc (`t ≥ S`). **Due thấp** nếu duePool < 1×N → **30%** mới; **due vừa** nếu 1×N ≤ duePool < 3×N → **20%**; **due cao** nếu duePool ≥ 3×N → **10%**. Học viên mới (chưa chấm câu nào): **100%** mới. Phiên này: **'.$this->dueBandLabel((string) ($quota['due_band'] ?? 'low'), (int) ($group['due_pool'] ?? 0), max(1, (int) ($quota['new_count'] ?? 0) + (int) ($quota['review_slots'] ?? 0))).'** → **'.((string) ($quota['new_count'] ?? '—')).'** mới / **'.((string) ($quota['review_slots'] ?? '—')).'** ôn.'],
            ['name' => '③ Phân suất — mode '.$focus['label'], 'expr' => 'Chỉ chia suất ôn: Điểm yếu ≈ 100% Yếu; Củng cố ≈ 100% Sắp quên; Cân bằng ≈ 50/50. Suất yếu/due: **'.((string) ($quota['weak_quota'] ?? '—')).' / '.((string) ($quota['due_quota'] ?? '—')).'**. Câu mới ưu tiên **bài dang dở**.'],
        ];
    }

    /**
     * @param  list<array{at: string, steps: array<string, array{at: string, step: string, context: array<string, mixed>}>}>  $runs
     */
    private function loadLabels(array $runs): void
    {
        $userIds = [];
        $blueprintIds = [];
        $questionIds = [];
        $organIds = [];
        $subjectIds = [];

        foreach ($runs as $run) {
            foreach ($run['steps'] as $step) {
                $context = $step['context'];
                $this->collect($userIds, $context['user_id'] ?? null);
                $this->collect($blueprintIds, $context['blueprint_id'] ?? null);
                foreach (['organ_system_ids', 'subject_ids', 'question_ids', 'picked'] as $key) {
                    foreach ((array) ($context[$key] ?? []) as $id) {
                        if ($key === 'organ_system_ids') {
                            $this->collect($organIds, $id);
                        } elseif ($key === 'subject_ids') {
                            $this->collect($subjectIds, $id);
                        } elseif ($key === 'question_ids' || $key === 'picked') {
                            $this->collect($questionIds, $id);
                        }
                    }
                }
                foreach (['top', 'sheet', 'ranking', 'items', 'resting'] as $list) {
                    foreach ((array) ($context[$list] ?? []) as $row) {
                        if (is_array($row)) {
                            $this->collect($questionIds, $row['question_id'] ?? null);
                        }
                    }
                }
            }
        }

        if ($userIds !== []) {
            $this->learners = User::query()->whereIn('id', array_keys($userIds))->pluck('name', 'id')->all();
        }
        if ($blueprintIds !== []) {
            $this->blueprints = Blueprint::query()->whereIn('id', array_keys($blueprintIds))->pluck('name', 'id')->all();
        }
        if ($questionIds !== []) {
            $this->questions = Question::query()->whereIn('id', array_keys($questionIds))->pluck('code', 'id')->all();
        }
        if ($organIds !== []) {
            $this->organs = OrganSystem::query()->whereIn('id', array_keys($organIds))->pluck('name', 'id')->all();
        }
        if ($subjectIds !== []) {
            $this->subjects = Subject::query()->whereIn('id', array_keys($subjectIds))->pluck('name', 'id')->all();
        }
    }

    /**
     * @param  array<int|string, true>  $bucket
     */
    private function collect(array &$bucket, mixed $id): void
    {
        if ($id === null || $id === '') {
            return;
        }

        $bucket[(string) $id] = true;
    }

    /**
     * @param  array<string, mixed>  $start
     * @param  array<string, mixed>  $pool
     * @param  array<string, mixed>  $split
     * @param  array<string, mixed>|null  $topUp
     */
    private function summary(
        bool $isLegacy,
        bool $empty,
        string $intent,
        array $start,
        array $pool,
        array $split,
        ?array $topUp,
    ): string {
        if ($isLegacy) {
            return 'Học viên luyện một bài học cụ thể. Hệ thống ưu tiên những câu đang trả lời sai trong bài đó.';
        }

        if ($empty) {
            return 'Bộ lọc hiện tại không còn câu đã xuất bản để bắt đầu phiên.';
        }

        $scope = match ((string) ($pool['scope'] ?? 'blueprint_membership')) {
            'organ_subject' => 'Phạm vi là hệ và môn đã chọn.',
            'matrix_intersect_organ_subject', 'blueprint_and_content' => 'Phạm vi là phần giao giữa đề thi với hệ và môn đã chọn.',
            'empty_content_filter' => 'Hệ hoặc môn đã chọn không nằm trong đề.',
            'all' => 'Phạm vi là mọi câu đã xuất bản.',
            default => 'Phạm vi là toàn bộ đề thi.',
        };

        $filters = $this->filterNames($start);
        if ($filters !== '') {
            $scope .= ' '.$filters;
        }

        if (($start['can_use_premium'] ?? true) === false || ($pool['premium_only_free'] ?? false) === true) {
            $scope .= ' Tài khoản chỉ được lấy câu miễn phí.';
        }

        $unseen = (int) ($split['unseen_count'] ?? 0);
        $seen = (int) ($split['seen_count'] ?? 0);
        $mix = match (true) {
            $unseen === 0 && $seen > 0 => ' Học viên đã được chấm hết các câu trong phạm vi này, nên cả phiên dùng để ôn lại.',
            $seen === 0 => ' Học viên chưa có câu nào được chấm trong phạm vi này, nên cả phiên là câu chưa chấm.',
            default => " Trong phạm vi còn {$unseen} câu chưa chấm và {$seen} câu đã chấm. Phiên dành ".(int) ($split['quota_unseen'] ?? 0).' chỗ cho câu chưa chấm và '.(int) ($split['quota_review'] ?? 0).' chỗ để ôn câu đã chấm.',
        };

        $filled = (int) ($topUp['filled'] ?? 0);
        $extra = $topUp !== null && $filled > 0
            ? " Nhóm ôn không đủ câu, nên hệ thống bốc thêm {$filled} câu trong phần còn lại của đề."
            : '';

        return $intent.' '.$scope.$mix.$extra;
    }

    /**
     * @param  array<string, mixed>  $sampled
     * @param  array<string, mixed>  $scores
     * @return list<array{code: string, why: string}>
     */
    private function reasons(array $sampled, array $scores): array
    {
        /** @var array<string, array<string, mixed>> $byId */
        $byId = [];
        foreach ((array) ($scores['top'] ?? []) as $row) {
            if (is_array($row) && isset($row['question_id'])) {
                $byId[(string) $row['question_id']] = $row;
            }
        }

        $reasons = [];
        foreach ((array) ($sampled['picked'] ?? []) as $id) {
            $id = (string) $id;
            $row = $byId[$id] ?? null;
            $reasons[] = [
                'code' => $this->label($this->questions, $id, 'Một câu'),
                'why' => $row === null
                    ? 'Được đưa vào nhóm ôn vì học viên đã gặp câu này trước đó. Hệ thống bốc thăm theo mức ưu tiên.'
                    : $this->why($row),
            ];
        }

        return $reasons;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function why(array $row): string
    {
        $wrong = (int) ($row['wrong'] ?? 0);
        $correct = (int) ($row['correct'] ?? 0);
        $attempts = $wrong + $correct;
        $weakness = (float) ($row['weakness'] ?? 0);

        $skill = match (true) {
            $attempts === 0 => 'Chưa có lần chấm đúng hay sai',
            $weakness >= 0.6 => "Hay trả lời sai ({$wrong} sai / {$attempts} lần)",
            $weakness >= 0.4 => "Chưa vững ({$wrong} sai / {$attempts} lần)",
            default => "Đã làm đúng khá ổn ({$correct} đúng / {$attempts} lần)",
        };

        $days = (float) ($row['days_since_seen'] ?? 0);
        $memory = match (true) {
            $days >= 30 => 'đã hơn một tháng kể từ lần chấm',
            $days >= 7 => 'đã '.(int) round($days).' ngày kể từ lần chấm',
            $days >= 1 => 'mới chấm khoảng '.(int) round($days).' ngày trước',
            default => 'vừa được chấm trong ngày',
        };

        $cooldown = (float) ($row['cooldown'] ?? 1);
        $hold = match (true) {
            $cooldown <= 0.15 => ' Trong vòng hai ngày câu nằm trong phiên mới nhất, nên cơ hội được chọn lại rất thấp.',
            $cooldown <= 0.35 => ' Trong vòng hai ngày câu không nằm ở phiên mới nhất, nên bị giảm cơ hội chọn lại.',
            default => '',
        };

        return $skill.', '.$memory.'.'.$hold;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $sampled
     * @return list<string>
     */
    private function freshQuestions(array $result, array $sampled): array
    {
        $review = array_flip(array_map(strval(...), (array) ($sampled['picked'] ?? [])));
        $fresh = [];
        foreach ((array) ($result['question_ids'] ?? []) as $id) {
            $id = (string) $id;
            if (! isset($review[$id])) {
                $fresh[] = $this->label($this->questions, $id, 'Một câu mới');
            }
        }

        return $fresh;
    }

    private function closingV2(bool $empty, bool $served, bool $graded): string
    {
        if ($empty) {
            return 'Không có câu để chọn trong phạm vi này.';
        }
        if ($graded) {
            return 'Phiên đã hoàn thành — bảng chấm điểm ở dưới để đối chiếu S / W / đúng-sai.';
        }
        if ($served) {
            return 'Đã tạo phiên. Làm xong và nộp bài để thấy bảng chấm điểm (S trước → sau).';
        }

        return 'Log chọn câu chưa ghi bước served.';
    }

    private function closing(bool $isLegacy, bool $empty, bool $served): string
    {
        if ($empty) {
            return 'Phiên không được tạo.';
        }

        if (! $served) {
            return 'Lần chọn này chưa ghi nhận xong.';
        }

        if ($isLegacy) {
            return 'Các câu sai được giữ lại để học viên luyện tiếp trong bài đã chọn.';
        }

        return 'Các câu vừa chọn được ghi nhận. Lần luyện sau sẽ tạm tránh lặp lại chúng, để học viên gặp nội dung mới hoặc chỗ cần ôn hơn.';
    }

    /**
     * @param  array<string, mixed>  $start
     */
    private function filterNames(array $start): string
    {
        $names = [];
        foreach ((array) ($start['organ_system_ids'] ?? []) as $id) {
            $name = $this->organs[(string) $id] ?? $this->organs[(int) $id] ?? null;
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }
        foreach ((array) ($start['subject_ids'] ?? []) as $id) {
            $name = $this->subjects[(string) $id] ?? $this->subjects[(int) $id] ?? null;
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        if ($names === []) {
            return '';
        }

        return 'Đã chọn: '.implode(', ', $names).'.';
    }

    /**
     * @return array{label: string, intent: string, tone: string}
     */
    private function focus(string $focus): array
    {
        return match ($focus) {
            'weak_focus' => [
                'label' => 'Điểm yếu',
                'intent' => 'Hướng luyện này ưu tiên câu hay trả lời sai, và vẫn dành một phần cho câu đang dễ quên.',
                'tone' => 'rose',
            ],
            'retention' => [
                'label' => 'Củng cố',
                'intent' => 'Hướng luyện này ưu tiên câu có mức cần ôn cao: độ bền thấp hoặc đã lâu kể từ lần chấm.',
                'tone' => 'amber',
            ],
            default => [
                'label' => 'Cân bằng',
                'intent' => 'Hướng luyện này cân giữa câu hay sai và câu đang dễ quên.',
                'tone' => 'sky',
            ],
        };
    }

    private function headline(
        bool $isLegacy,
        bool $empty,
        string $learner,
        string $focus,
        string $blueprint,
        int $picked,
        int $poolSize,
    ): string {
        if ($empty) {
            return "{$learner} chưa bắt đầu được phiên trên {$blueprint}.";
        }

        if ($isLegacy) {
            return "{$learner} luyện {$picked} câu đang sai trong một bài học.";
        }

        $pool = $poolSize > 0 ? " từ {$poolSize} câu của {$blueprint}" : " trong {$blueprint}";

        return "{$learner} nhận {$picked} câu hướng {$focus}{$pool}.";
    }

    private function when(string $at): string
    {
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $at, new DateTimeZone('Asia/Ho_Chi_Minh'))
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', substr($at, 0, 19));

        if (! $parsed instanceof DateTimeImmutable) {
            return $at;
        }

        return $parsed->format('H:i, d/m/Y');
    }

    /**
     * @param  array<int|string, string>  $map
     */
    /**
     * Priority table for one session. Highest priority first.
     *
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $scores
     * @param  array<string, mixed>  $sampled
     * @return list<array<string, string>>
     */
    private function sessionTable(array $result, array $scores, array $sampled): array
    {
        $source = (array) ($result['ranking'] ?? []);
        if ($source === []) {
            $source = (array) ($result['sheet'] ?? []);
        }

        /** @var array<string, array<string, mixed>> $byId */
        $byId = [];
        foreach ((array) ($scores['top'] ?? []) as $row) {
            if (is_array($row) && isset($row['question_id'])) {
                $byId[(string) $row['question_id']] = $row;
            }
        }

        $selectedIds = [];
        foreach ((array) ($result['question_ids'] ?? $sampled['picked'] ?? []) as $id) {
            $selectedIds[(string) $id] = true;
        }
        foreach ((array) ($sampled['picked'] ?? []) as $id) {
            $selectedIds[(string) $id] = true;
        }

        if ($source === []) {
            foreach ($byId as $id => $row) {
                $source[] = $row;
            }
            foreach (array_keys($selectedIds) as $id) {
                if (! isset($byId[$id])) {
                    $source[] = [
                        'question_id' => $id,
                        'kind' => 'fresh',
                        'selected' => true,
                        'correct' => 0,
                        'wrong' => 0,
                        'weight' => null,
                    ];
                }
            }
        }

        $rows = [];
        foreach ($source as $row) {
            if (! is_array($row) || ! isset($row['question_id'])) {
                continue;
            }

            $id = (string) $row['question_id'];
            if (! array_key_exists('weight', $row) && isset($byId[$id])) {
                $row = [...$byId[$id], ...$row];
            }

            $selected = array_key_exists('selected', $row)
                ? (bool) $row['selected']
                : isset($selectedIds[$id]);
            $hasPriority = array_key_exists('weight', $row) && $row['weight'] !== null;
            $rows[] = [
                'code' => $this->label($this->questions, $id, 'Một câu'),
                'weight' => $hasPriority ? (float) $row['weight'] : null,
                'selected' => $selected,
                'wrong' => (int) ($row['wrong'] ?? 0),
                'correct' => (int) ($row['correct'] ?? 0),
                'weakness' => isset($row['weakness']) && $row['weakness'] !== null ? (float) $row['weakness'] : null,
                'days' => isset($row['days_since_seen']) && $row['days_since_seen'] !== null ? (float) $row['days_since_seen'] : null,
                'stability' => isset($row['stability_days']) && $row['stability_days'] !== null ? (float) $row['stability_days'] : null,
                'memory' => isset($row['memory']) && $row['memory'] !== null ? (float) $row['memory'] : null,
                'cooldown' => isset($row['cooldown']) && $row['cooldown'] !== null ? (float) $row['cooldown'] : null,
                'role' => ($row['kind'] ?? '') === 'fresh' || ! $hasPriority ? 'Chưa chấm' : 'Ôn lại',
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            $leftWeight = $left['weight'] ?? -1.0;
            $rightWeight = $right['weight'] ?? -1.0;

            return [$rightWeight, $right['wrong'], $right['correct'], $left['code']]
                <=> [$leftWeight, $left['wrong'], $left['correct'], $right['code']];
        });

        $reviewWeight = 0.0;
        foreach ($rows as $row) {
            if ($row['weight'] !== null) {
                $reviewWeight += $row['weight'];
            }
        }

        $display = [];
        foreach ($rows as $index => $row) {
            $share = '—';
            if ($row['weight'] !== null && $reviewWeight > 0) {
                $share = $this->decimal(($row['weight'] / $reviewWeight) * 100).'%';
            }

            $display[] = [
                'rank' => (string) ($index + 1),
                'code' => (string) $row['code'],
                'priority' => $row['weight'] === null ? 'Chọn đều' : $this->decimal($row['weight']),
                'share' => $share,
                'chosen' => $row['selected'] ? 'Có' : 'Không',
                'wrong' => (string) $row['wrong'],
                'correct' => (string) $row['correct'],
                'weakness' => $row['weakness'] === null ? '—' : ((int) round($row['weakness'] * 100)).'%',
                'days' => $row['days'] === null ? '—' : $this->decimal($row['days']),
                'stability' => $this->decimal($row['stability']),
                'memory' => $this->decimal($row['memory']),
                'hold' => $this->holdLabel($row['cooldown']),
                'role' => (string) $row['role'],
            ];
        }

        return $display;
    }

    /**
     * Counts the questions actually placed in the session, including top-up.
     *
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $split
     * @return array{0: int, 1: int}
     */
    private function selectedCounts(array $result, array $split): array
    {
        $sheet = (array) ($result['sheet'] ?? []);
        if ($sheet !== []) {
            return $this->countKinds($sheet, selectedOnly: false);
        }

        $ranking = (array) ($result['ranking'] ?? []);
        if ($ranking !== []) {
            return $this->countKinds($ranking, selectedOnly: true);
        }

        return [
            (int) ($result['picked_unseen'] ?? $split['quota_unseen'] ?? 0),
            (int) ($result['picked_review'] ?? $split['quota_review'] ?? 0),
        ];
    }

    /**
     * @param  array<mixed>  $rows
     * @return array{0: int, 1: int}
     */
    private function countKinds(array $rows, bool $selectedOnly): array
    {
        $fresh = 0;
        $review = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ($selectedOnly && array_key_exists('selected', $row) && ! $row['selected']) {
                continue;
            }
            if (($row['kind'] ?? '') === 'fresh') {
                $fresh++;
            } else {
                $review++;
            }
        }

        return [$fresh, $review];
    }

    /**
     * @param  array{label: string, intent: string, tone: string}  $focus
     * @param  array<string, mixed>  $scores
     * @param  array<string, mixed>  $split
     * @return list<array{name: string, expr: string}>
     */
    private function formulas(array $focus, array $scores, array $split): array
    {
        $weaknessWeight = isset($scores['w_weakness']) && $scores['w_weakness'] !== null ? $this->decimal($scores['w_weakness']) : null;
        $memoryWeight = isset($scores['w_memory']) && $scores['w_memory'] !== null ? $this->decimal($scores['w_memory']) : null;
        $base = ($weaknessWeight !== null && $memoryWeight !== null)
            ? "**{$weaknessWeight} × độ yếu + {$memoryWeight} × mức cần ôn**. Điểm này đo câu cần được ôn nhiều đến mức nào, trước khi giảm vì vừa được chọn. Trọng số theo hướng {$focus['label']}."
            : '**trọng số độ yếu × độ yếu + trọng số mức cần ôn × mức cần ôn**. Điểm này đo câu cần được ôn nhiều đến mức nào, trước khi giảm vì vừa được chọn.';
        $reviewSlots = (int) ($split['quota_review'] ?? 0);
        $share = '**điểm ưu tiên của câu / tổng điểm ưu tiên của các câu đã chấm × 100**. Đây là suất ở vòng bốc đầu tiên.';
        if ($reviewSlots > 1) {
            $share .= " Phiên này bốc {$reviewSlots} câu đã chấm theo trọng số, không hoàn lại, nên các vòng sau tính trên phần còn lại.";
        } elseif ($reviewSlots === 1) {
            $share .= ' Phiên này bốc 1 câu đã chấm theo tỉ lệ này.';
        }

        return [
            ['name' => 'Độ yếu', 'expr' => 'Giả như câu đã có sẵn 1 lần đúng và 1 lần sai, rồi mới cộng các lần đã chấm: **(số lần sai + 1) / (số lần đúng + số lần sai + 2)**. Sai đúng một lần thì độ yếu là 67%, không phải 100%. Sai 8/10 thì là 75%. Câu bỏ qua thì không cộng vào.'],
            ['name' => 'Mức cần ôn', 'expr' => '**1 − e^(− số ngày từ lần chấm / độ bền)**. Càng gần 1 thì càng dễ quên. Lần chấm đầu đặt **độ bền = 1 ngày**. Đúng thì **×2**, sai thì **×0,3**, rồi kẹp từ **0,5 đến 365 ngày**. Câu chưa có độ bền thì **mức cần ôn = 1**.'],
            ['name' => 'Điểm cần ôn', 'expr' => $base],
            ['name' => 'Tránh lặp', 'expr' => '**×0,10** nếu trong 2 ngày câu đang nằm ở phiên mới nhất. **×0,30** nếu trong 2 ngày và đã có đúng một phiên tạo sau lần chọn. **×1,00** nếu chưa từng được chọn, nếu đã có từ hai phiên tạo sau lần chọn, hoặc nếu lần chọn đã quá 2 ngày.'],
            ['name' => 'Điểm ưu tiên', 'expr' => '**số lớn hơn giữa 0,01 và (điểm cần ôn × tránh lặp)**'],
            ['name' => '% suất vòng đầu', 'expr' => $share],
        ];
    }

    /**
     * @param  array{at: string, steps: array<string, array{at: string, step: string, context: array<string, mixed>}>}  $run
     * @return list<array{question_id: string, scored: bool, wrong: int, correct: int, weakness: float|null, days: float|null, memory: float|null, cooldown: float|null, weight: float|null}>
     */
    private function selectedEntries(array $run): array
    {
        $result = $run['steps']['result']['context'] ?? [];
        $served = $run['steps']['served']['context'] ?? [];
        /** @var array<string, array<string, mixed>> $scores */
        $scores = [];
        foreach ((array) ($run['steps']['review_scores']['context']['top'] ?? []) as $row) {
            if (is_array($row) && isset($row['question_id'])) {
                $scores[(string) $row['question_id']] = $row;
            }
        }

        $source = (array) ($result['sheet'] ?? []);
        if ($source === []) {
            $ids = (array) ($result['question_ids'] ?? $served['question_ids'] ?? []);
            foreach ($ids as $id) {
                $id = (string) $id;
                $source[] = $scores[$id] ?? [
                    'question_id' => $id,
                    'kind' => 'fresh',
                    'correct' => 0,
                    'wrong' => 0,
                ];
            }
        }

        $entries = [];
        foreach ($source as $row) {
            if (! is_array($row) || ! isset($row['question_id'])) {
                continue;
            }

            $id = (string) $row['question_id'];
            if (! array_key_exists('weakness', $row) && isset($scores[$id])) {
                $row = [...$scores[$id], ...$row];
            }

            $scored = array_key_exists('weakness', $row) && $row['weakness'] !== null;
            $entries[] = [
                'question_id' => $id,
                'scored' => $scored,
                'wrong' => (int) ($row['wrong'] ?? 0),
                'correct' => (int) ($row['correct'] ?? 0),
                'weakness' => $scored ? (float) $row['weakness'] : null,
                'days' => $scored && isset($row['days_since_seen']) ? (float) $row['days_since_seen'] : null,
                'memory' => $scored && isset($row['memory']) ? (float) $row['memory'] : null,
                'cooldown' => $scored && isset($row['cooldown']) ? (float) $row['cooldown'] : null,
                'weight' => $scored && isset($row['weight']) ? (float) $row['weight'] : null,
            ];
        }

        return $entries;
    }

    private function decimal(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format((float) $value, 2, ',', '.');
    }

    private function percent(float $value): string
    {
        return ((int) round($value * 100)).'%';
    }

    private function holdLabel(mixed $cooldown): string
    {
        if ($cooldown === null) {
            return '—';
        }

        $value = (float) $cooldown;
        $label = match (true) {
            $value <= 0.15 => 'Giảm mạnh',
            $value <= 0.35 => 'Giảm',
            default => 'Bình thường',
        };

        return $label.' ('.$this->decimal($value).')';
    }

    /**
     * @param  array<int|string, string>  $map
     */
    private function label(array $map, mixed $id, string $fallback): string
    {
        if ($id === null || $id === '') {
            return $fallback;
        }

        $name = $map[(string) $id] ?? $map[(int) $id] ?? null;

        return is_string($name) && $name !== '' ? $name : $fallback;
    }

    /**
     * @return array{at: string, step: string, context: array<string, mixed>}|null
     */
    private function parseLine(string $line): ?array
    {
        if (! str_contains($line, '[adaptive] ')) {
            return null;
        }

        if (preg_match('/^\[(?<at>[^\]]+)\].*\[adaptive\]\s+(?<step>[a-z0-9_]+)/', $line, $matches) !== 1) {
            return null;
        }

        $context = [];
        $jsonStart = strpos($line, '{');
        if ($jsonStart !== false) {
            $decoded = json_decode($this->jsonObject(substr($line, $jsonStart)), true);
            if (is_array($decoded)) {
                $context = $decoded;
            }
        }

        return [
            'at' => $matches['at'],
            'step' => $matches['step'],
            'context' => $context,
        ];
    }

    private function jsonObject(string $text): string
    {
        $depth = 0;
        $inString = false;
        $escape = false;
        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($inString) {
                if ($escape) {
                    $escape = false;

                    continue;
                }
                if ($char === '\\') {
                    $escape = true;

                    continue;
                }
                if ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;

                continue;
            }
            if ($char === '{') {
                $depth++;

                continue;
            }
            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, 0, $i + 1);
                }
            }
        }

        return $text;
    }

    private function tail(string $path): string
    {
        $maxBytes = 1_500_000;
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }

        $size = filesize($path);
        if (is_int($size) && $size > $maxBytes) {
            fseek($handle, -$maxBytes, SEEK_END);
            fgets($handle);
        }

        $contents = stream_get_contents($handle);
        fclose($handle);

        return is_string($contents) ? $contents : '';
    }
}
