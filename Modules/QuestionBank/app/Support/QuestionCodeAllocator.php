<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use Illuminate\Support\Facades\DB;

/**
 * Allocates immutable sequential question codes: Q00001, Q00002, …
 *
 * Uses a locked counter row so concurrent creates never share a code.
 * Failed creates may leave gaps; that is intentional.
 */
final class QuestionCodeAllocator
{
    public function allocate(): string
    {
        return DB::transaction(function (): string {
            $sequence = $this->lockedNextValue();

            DB::table('question_code_sequences')
                ->where('id', 1)
                ->update([
                    'next_value' => $sequence + 1,
                    'updated_at' => now(),
                ]);

            return $this->format($sequence);
        });
    }

    /**
     * Raise the counter when an explicit Q##### code is inserted (e.g. imports).
     */
    public function ensureAtLeast(int $minimumNext): void
    {
        if ($minimumNext < 1) {
            return;
        }

        DB::transaction(function () use ($minimumNext): void {
            $current = $this->lockedNextValue();
            if ($current >= $minimumNext) {
                return;
            }

            DB::table('question_code_sequences')
                ->where('id', 1)
                ->update([
                    'next_value' => $minimumNext,
                    'updated_at' => now(),
                ]);
        });
    }

    public function format(int $sequence): string
    {
        return sprintf('Q%05d', $sequence);
    }

    private function lockedNextValue(): int
    {
        $row = DB::table('question_code_sequences')
            ->where('id', 1)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            DB::table('question_code_sequences')->insert([
                'id' => 1,
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return 1;
        }

        return (int) $row->next_value;
    }
}
