<?php

declare(strict_types=1);

namespace Modules\Exam\Services;

/** Integral maximum flow: group -> unique question -> topic. */
final class ExamQuotaMatcher
{
    public function quotas(int $total): array
    {
        $weights = ['easy' => 4, 'medium' => 3, 'hard' => 3];
        $counts = $remainders = [];
        foreach ($weights as $key => $weight) {
            $counts[$key] = intdiv($total * $weight, 10);
            $remainders[$key] = ($total * $weight) % 10;
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
    public function match(array $topics): array
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
        foreach ($this->quotas($total) as $group => $quota) {
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

        return ['complete' => $flow === $total, 'selected' => $selected, 'quotas' => $this->quotas($total)];
    }
}
