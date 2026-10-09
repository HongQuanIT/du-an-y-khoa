<?php

declare(strict_types=1);

namespace Modules\Exam\Services;

/** Integral maximum flow: group -> unique question -> topic. */
final class ExamQuotaMatcher
{
    /** @param array{easy: int, medium: int, hard: int}|null $weights */
    public function quotas(int $total, ?array $weights = null): array
    {
        $weights ??= ['easy' => 40, 'medium' => 30, 'hard' => 30];
        $counts = $remainders = [];
        foreach ($weights as $key => $weight) {
            $counts[$key] = intdiv($total * $weight, 100);
            $remainders[$key] = ($total * $weight) % 100;
        }
        arsort($remainders);
        $left = $total - array_sum($counts);
        foreach (array_keys($remainders) as $key) {
            if ($left-- <= 0) {
                break;
            }
            $counts[$key]++;
        }

        return $counts;
    }

    /** @param array<int, array{count: int, candidates: array<string, string>}> $topics */
    /**
     * @param array<int|string, array{count: int, candidates: array<string, string>}> $topics
     * @param array{easy: int, medium: int, hard: int}|null $weights
     */
    public function match(array $topics, ?array $weights = null): array
    {
        $capacity = $adjacency = [];
        $edge = static function (string $from, string $to, int $amount) use (&$capacity, &$adjacency): void {
            if (isset($capacity[$from][$to])) {
                return;
            }
            $capacity[$from][$to] = $amount;
            $capacity[$to][$from] = 0;
            $adjacency[$from][] = $to;
            $adjacency[$to][] = $from;
        };
        $total = array_sum(array_column($topics, 'count'));
        foreach ($this->quotas($total, $weights) as $group => $quota) {
            $edge('source', 'g:'.$group, $quota);
        }
        foreach ($topics as $topicId => $topic) {
            $edge('t:'.$topicId, 'sink', $topic['count']);
            foreach ($topic['candidates'] as $id => $group) {
                $edge('g:'.$group, 'q:'.$id, 1);
                $edge('q:'.$id, 't:'.$topicId, 1);
            }
        }
        $flow = 0;
        while ($flow < $total) {
            $parents = ['source' => null];
            $queue = ['source'];
            for ($i = 0; $i < count($queue) && ! isset($parents['sink']); $i++) {
                $node = $queue[$i];
                foreach ($adjacency[$node] ?? [] as $next) {
                    if (array_key_exists($next, $parents) || $capacity[$node][$next] <= 0) {
                        continue;
                    }
                    $parents[$next] = $node;
                    $queue[] = $next;
                }
            }
            if (! isset($parents['sink'])) {
                break;
            }
            for ($node = 'sink'; $node !== 'source'; $node = $parents[$node]) {
                $previous = $parents[$node];
                $capacity[$previous][$node]--;
                $capacity[$node][$previous]++;
            }
            $flow++;
        }
        $selected = [];
        foreach ($topics as $topicId => $topic) {
            foreach ($topic['candidates'] as $id => $group) {
                if (($capacity['t:'.$topicId]['q:'.$id] ?? 0) > 0) {
                    $selected[$id] = $topicId;
                }
            }
        }

        return ['complete' => $flow === $total, 'selected' => $selected, 'quotas' => $this->quotas($total, $weights)];
    }

    /**
     * Spread each difficulty across topics while preserving the global quotas,
     * topic sizes, and the rule that a question can appear only once.
     *
     * @param array<int|string, array{count: int, candidates: array<string, string>}> $topics
     * @param array{easy: int, medium: int, hard: int}|null $weights
     */
    public function matchBalanced(array $topics, ?array $weights = null, ?array $targets = null): array
    {
        $weights ??= ['easy' => 40, 'medium' => 30, 'hard' => 30];
        $total = array_sum(array_column($topics, 'count'));
        $quotas = $this->quotas($total, $weights);
        $nodes = ['source' => 0, 'sink' => 1];
        $graph = [[], []];
        $node = static function (string $key) use (&$nodes, &$graph): int {
            if (! isset($nodes[$key])) {
                $nodes[$key] = count($graph);
                $graph[] = [];
            }

            return $nodes[$key];
        };
        $edge = static function (int $from, int $to, int $capacity, int $cost) use (&$graph): int {
            $forward = count($graph[$from]);
            $reverse = count($graph[$to]);
            $graph[$from][] = ['to' => $to, 'reverse' => $reverse, 'capacity' => $capacity, 'cost' => $cost];
            $graph[$to][] = ['to' => $from, 'reverse' => $forward, 'capacity' => 0, 'cost' => -$cost];

            return $forward;
        };

        foreach ($quotas as $group => $quota) {
            $edge(0, $node('g:'.$group), $quota, 0);
        }

        $questionNodes = $assignmentEdges = [];
        foreach ($topics as $topicId => $topic) {
            $topicNode = $node('t:'.$topicId);
            $edge($topicNode, 1, $topic['count'], 0);
            $available = array_count_values($topic['candidates']);
            foreach ($quotas as $group => $quota) {
                $cellNode = $node('c:'.$topicId.':'.$group);
                $limit = min($topic['count'], $quota, $available[$group] ?? 0);
                for ($slot = 1; $slot <= $limit; $slot++) {
                    // Convex cost: filling an already represented difficulty costs more.
                    // The constant keeps initial costs nonnegative and does not affect
                    // the result because every topic always takes its fixed question count.
                    $target = $targets[$topicId][$group] ?? $topic['count'] * $weights[$group] / 100;
                    $cost = (int) round(200 * $slot + 200 * ($topic['count'] - $target)) + random_int(0, 39);
                    $edge($cellNode, $topicNode, 1, $cost);
                }
            }
            $candidateCount = max(1, count($topic['candidates']));
            $rank = 0;
            foreach ($topic['candidates'] as $id => $group) {
                $questionKey = (string) $id;
                if (! isset($questionNodes[$questionKey])) {
                    $questionNodes[$questionKey] = $node('q:'.$questionKey);
                    $edge($node('g:'.$group), $questionNodes[$questionKey], 1, 0);
                }
                $questionNode = $questionNodes[$questionKey];
                $assignmentEdges[$topicId][$questionKey] = [
                    $questionNode,
                    $edge($questionNode, $node('c:'.$topicId.':'.$group), 1, intdiv(10 * $rank++, $candidateCount) + random_int(0, 2)),
                ];
            }
        }

        $potentials = array_fill(0, count($graph), 0);
        $flow = 0;
        while ($flow < $total) {
            $distances = array_fill(0, count($graph), PHP_INT_MAX);
            $parents = [];
            $distances[0] = 0;
            $queue = new \SplPriorityQueue;
            $queue->setExtractFlags(\SplPriorityQueue::EXTR_DATA);
            $queue->insert([0, 0], 0);

            while (! $queue->isEmpty()) {
                [$from, $distance] = $queue->extract();
                if ($distance !== $distances[$from]) {
                    continue;
                }
                foreach ($graph[$from] as $index => $candidate) {
                    if ($candidate['capacity'] <= 0) {
                        continue;
                    }
                    $to = $candidate['to'];
                    $nextDistance = $distance + $candidate['cost'] + $potentials[$from] - $potentials[$to];
                    if ($nextDistance >= $distances[$to]) {
                        continue;
                    }
                    $distances[$to] = $nextDistance;
                    $parents[$to] = [$from, $index];
                    $queue->insert([$to, $nextDistance], -$nextDistance);
                }
            }

            if (! isset($parents[1])) {
                break;
            }
            foreach ($distances as $index => $distance) {
                if ($distance !== PHP_INT_MAX) {
                    $potentials[$index] += $distance;
                }
            }
            for ($to = 1; $to !== 0; $to = $from) {
                [$from, $index] = $parents[$to];
                $reverse = $graph[$from][$index]['reverse'];
                $graph[$from][$index]['capacity']--;
                $graph[$to][$reverse]['capacity']++;
            }
            $flow++;
        }

        $selected = [];
        foreach ($assignmentEdges as $topicId => $questions) {
            foreach ($questions as $id => [$questionNode, $index]) {
                if ($graph[$questionNode][$index]['capacity'] === 0) {
                    $selected[$id] = $topicId;
                }
            }
        }

        return ['complete' => $flow === $total, 'selected' => $selected, 'quotas' => $quotas];
    }

    /**
     * Try several different allocations across all topics and keep the feasible
     * one whose difficulty table differs most from the previous paper.
     *
     * @param array<int|string, array{count: int, candidates: array<string, string>}> $topics
     * @param array<int|string, array{easy: int, medium: int, hard: int}> $previousCounts
     * @param array{complete: bool, selected: array<string, int|string>, quotas: array{easy: int, medium: int, hard: int}} $baseline
     */
    public function varyBalanced(array $topics, array $previousCounts, array $baseline, ?array $weights = null): array
    {
        if (! $baseline['complete'] || $previousCounts === []) {
            return $baseline;
        }

        $weights ??= ['easy' => 40, 'medium' => 30, 'hard' => 30];
        $reference = [];
        foreach ($topics as $topicId => $topic) {
            $counts = $previousCounts[$topicId] ?? null;
            if ($counts === null || array_sum($counts) !== $topic['count']) {
                return $baseline;
            }
            $reference[$topicId] = $counts;
        }
        foreach ($baseline['quotas'] as $group => $quota) {
            if (array_sum(array_column($reference, $group)) !== $quota) {
                return $baseline;
            }
        }

        $best = $baseline;
        $bestDistance = $this->distributionDistance($topics, $baseline['selected'], $reference);
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $targets = $this->shuffledTargets($topics, $reference, $weights);
            if ($targets === $reference) {
                continue;
            }
            $candidate = $this->matchBalanced($topics, $weights, $targets);
            if (! $candidate['complete']) {
                continue;
            }
            $distance = $this->distributionDistance($topics, $candidate['selected'], $reference);
            if ($distance > $bestDistance) {
                $best = $candidate;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * @param array<int|string, array{count: int, candidates: array<string, string>}> $topics
     * @param array<int|string, array{easy: int, medium: int, hard: int}> $previous
     * @param array{easy: int, medium: int, hard: int} $weights
     * @return array<int|string, array{easy: int, medium: int, hard: int}>
     */
    private function shuffledTargets(array $topics, array $previous, array $weights): array
    {
        $targets = $previous;
        $topicIds = array_keys($topics);
        $groups = array_keys($weights);
        if (count($topicIds) < 2) {
            return $targets;
        }

        $changes = 0;
        for ($attempt = 0; $attempt < 80 && $changes < min(8, count($topicIds)); $attempt++) {
            $first = $topicIds[random_int(0, count($topicIds) - 1)];
            $second = $topicIds[random_int(0, count($topicIds) - 1)];
            if ($first === $second) {
                continue;
            }
            $give = $groups[random_int(0, count($groups) - 1)];
            $take = $groups[random_int(0, count($groups) - 1)];
            if ($give === $take || $targets[$first][$give] <= 0 || $targets[$second][$take] <= 0) {
                continue;
            }
            if (! $this->canIncrease($topics[$first], $weights, $take, $targets[$first][$take])
                || ! $this->canIncrease($topics[$second], $weights, $give, $targets[$second][$give])) {
                continue;
            }
            if (! $this->canDecrease($topics[$first], $weights, $give, $targets[$first][$give])
                || ! $this->canDecrease($topics[$second], $weights, $take, $targets[$second][$take])) {
                continue;
            }
            $targets[$first][$give]--;
            $targets[$first][$take]++;
            $targets[$second][$take]--;
            $targets[$second][$give]++;
            $changes++;
        }

        return $targets;
    }

    private function canIncrease(array $topic, array $weights, string $group, int $current): bool
    {
        $available = array_count_values($topic['candidates'])[$group] ?? 0;
        $idealMaximum = (int) ceil($topic['count'] * $weights[$group] / 100) + 1;

        return $current < min($topic['count'], $available, max($idealMaximum, $current));
    }

    private function canDecrease(array $topic, array $weights, string $group, int $current): bool
    {
        $available = array_count_values($topic['candidates'])[$group] ?? 0;
        $minimum = $topic['count'] >= 3 && $weights[$group] > 0 && $available > 0 ? 1 : 0;

        return $current > $minimum;
    }

    /**
     * @param array<int|string, array{count: int, candidates: array<string, string>}> $topics
     * @param array<string, int|string> $selected
     * @param array<int|string, array{easy: int, medium: int, hard: int}> $previous
     */
    private function distributionDistance(array $topics, array $selected, array $previous): int
    {
        $counts = [];
        foreach ($topics as $topicId => $_) {
            $counts[$topicId] = ['easy' => 0, 'medium' => 0, 'hard' => 0];
        }
        foreach ($selected as $questionId => $topicId) {
            $group = $topics[$topicId]['candidates'][$questionId];
            $counts[$topicId][$group]++;
        }
        $distance = 0;
        foreach ($counts as $topicId => $groups) {
            foreach ($groups as $group => $count) {
                $distance += abs($count - $previous[$topicId][$group]);
            }
        }

        return $distance;
    }
}
