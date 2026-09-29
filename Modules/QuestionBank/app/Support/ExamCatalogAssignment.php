<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use Illuminate\Validation\ValidationException;
use Modules\QuestionBank\Models\ExamCatalog;

/**
 * A question's kỳ thi must belong to at least one of the question's đối tượng.
 */
final class ExamCatalogAssignment
{
    /**
     * @param  list<int>  $professionIds
     * @param  list<int>  $examCatalogIds
     */
    public static function assertMatchesProfessions(array $professionIds, array $examCatalogIds): void
    {
        $examCatalogIds = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $examCatalogIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($examCatalogIds === []) {
            return;
        }

        $professionIds = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $professionIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($professionIds === []) {
            throw ValidationException::withMessages([
                'exam_catalog_ids' => 'Chọn đối tượng trước khi gắn kỳ thi.',
            ]);
        }

        $allowed = ExamCatalog::query()
            ->whereIn('id', $examCatalogIds)
            ->where(function ($query) use ($professionIds): void {
                $query->whereDoesntHave('professions')
                    ->orWhereHas(
                        'professions',
                        fn ($professions) => $professions->whereIn('professions.id', $professionIds),
                    );
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (count($allowed) !== count($examCatalogIds)) {
            throw ValidationException::withMessages([
                'exam_catalog_ids' => 'Kỳ thi phải thuộc một đối tượng đang chọn trên câu hỏi.',
            ]);
        }
    }
};
