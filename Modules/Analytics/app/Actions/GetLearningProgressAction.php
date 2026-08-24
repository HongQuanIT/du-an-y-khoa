<?php

declare(strict_types=1);

namespace Modules\Analytics\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Modules\Analytics\Models\DailyLearningStat;

final class GetLearningProgressAction
{
    use AsAction;

    /** @return array{range: string, points: list<array{date: string, label: string, questions: int, correct: int, accuracy: int, height: int}>, max: int} */
    public function handle(User $user, string $range = '30d'): array
    {
        $range = in_array($range, ['7d', '30d', 'all'], true) ? $range : '30d';
        $lastDate = Carbon::today();
        $firstDate = match ($range) {
            '7d' => $lastDate->copy()->subDays(6),
            'all' => $this->firstActivityDate($user) ?? $lastDate->copy()->subDays(29),
            default => $lastDate->copy()->subDays(29),
        };

        // Keep the server-rendered chart readable for long-lived accounts.
        if ($firstDate->diffInDays($lastDate) > 364) {
            $firstDate = $lastDate->copy()->subDays(364);
        }

        $stats = DailyLearningStat::query()
            ->where('user_id', $user->getKey())
            ->whereBetween('date', [$firstDate->toDateString(), $lastDate->toDateString()])
            ->get()
            ->keyBy(fn (DailyLearningStat $stat): string => $stat->date->toDateString());
        $max = max(1, (int) $stats->max('questions_answered'));
        $points = collect();

        for ($date = $firstDate->copy(); $date->lte($lastDate); $date->addDay()) {
            $stat = $stats->get($date->toDateString());
            $questions = $stat?->questions_answered ?? 0;
            $correct = $stat?->correct_answers ?? 0;
            $points->push([
                'date' => $date->toDateString(),
                'label' => $date->isToday() ? 'Hôm nay' : $date->locale('vi')->translatedFormat('d M'),
                'questions' => $questions,
                'correct' => $correct,
                'accuracy' => $questions > 0 ? (int) round($correct / $questions * 100) : 0,
                'height' => $questions > 0 ? max(6, (int) round($questions / $max * 100)) : 0,
            ]);
        }

        return ['range' => $range, 'points' => $points->values()->all(), 'max' => $max];
    }

    private function firstActivityDate(User $user): ?Carbon
    {
        $date = DailyLearningStat::query()->where('user_id', $user->getKey())->min('date');

        return $date !== null ? Carbon::parse($date)->startOfDay() : null;
    }
}
