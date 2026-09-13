<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Admin\Actions\BuildQuestionExportRowsAction;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Support\AdminQuestionListQuery;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\QuestionAccess;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Support\QuestionExportLimits;
use Modules\QuestionBank\Support\QuestionImportSchema;
use Modules\QuestionBank\Support\QuestionSpreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class QuestionExportController extends Controller
{
    public function __invoke(
        Request $request,
        AdminQuestionListQuery $listQuery,
        BuildQuestionExportRowsAction $exportRows,
        QuestionSpreadsheet $spreadsheet,
    ): StreamedResponse {
        abort_unless(QuestionAccess::canAccessWorkspace($this->actor()), 403);

        $ids = $this->selectedIds($request);
        $query = $listQuery->apply(Question::query(), $request, $this->actor());
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $matched = (clone $query)->count();
        $questions = $this->loadExportRows($query, $ids);
        $export = $exportRows->handle($questions);
        $format = $request->input('format') === 'csv' ? 'csv' : 'xlsx';
        $filename = 'cau-hoi-'.now()->format('Ymd-His').'.'.$format;
        $notice = QuestionExportLimits::notice($questions->count(), $matched);

        $guide = QuestionImportSchema::guideRows();
        if ($notice !== null) {
            array_splice($guide, 1, 0, [['Giới hạn xuất', '—', $notice]]);
        }
        if ($ids !== []) {
            array_splice($guide, 1, 0, [[
                'Phạm vi xuất',
                '—',
                'Xuất '.$questions->count().' câu đã chọn trên danh sách.',
            ]]);
        }

        Auditor::record(
            AuditAction::QuestionExported,
            $this->actor(),
            metadata: [
                'count' => $questions->count(),
                'matched' => $matched,
                'selected' => count($ids),
                'format' => $format,
                'filters' => $request->except(['format', 'ids', '_token']),
            ],
        );

        return response()->streamDownload(function () use ($spreadsheet, $format, $export, $guide): void {
            if ($format === 'csv') {
                echo $spreadsheet->csvString($export['headers'], $export['rows']);

                return;
            }

            echo $spreadsheet->xlsxBinary($export['headers'], $export['rows'], $guide);
        }, $filename, [
            'Content-Type' => $format === 'csv'
                ? 'text/csv; charset=UTF-8'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return list<string>
     */
    private function selectedIds(Request $request): array
    {
        $raw = $request->input('ids', []);
        if (! is_array($raw)) {
            $raw = [$raw];
        }

        return collect($raw)
            ->map(fn (mixed $id): string => trim((string) $id))
            ->filter(fn (string $id): bool => $id !== '')
            ->unique()
            ->take(QuestionExportLimits::MAX_ROWS)
            ->values()
            ->all();
    }

    /**
     * @param  Builder<Question>  $query
     * @param  list<string>  $ids
     * @return Collection<int, Question>
     */
    private function loadExportRows(Builder $query, array $ids): Collection
    {
        $questions = $query
            ->with(['options', 'lessons', 'tags', 'hints'])
            ->latest('updated_at')
            ->limit(QuestionExportLimits::MAX_ROWS)
            ->get();

        if ($ids === []) {
            return $questions;
        }

        $order = array_flip($ids);

        return $questions
            ->sortBy(fn (Question $question): int => $order[$question->getKey()] ?? PHP_INT_MAX)
            ->values();
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
