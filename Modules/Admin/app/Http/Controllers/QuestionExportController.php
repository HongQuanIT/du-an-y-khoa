<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\Admin\Actions\BuildQuestionExportRowsAction;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Support\AdminQuestionListQuery;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\QuestionAccess;
use Modules\QuestionBank\Models\Question;
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

        $query = $listQuery->apply(Question::query(), $request, $this->actor());
        $questions = $query
            ->with(['options', 'lessons', 'tags', 'hints'])
            ->latest('updated_at')
            ->limit(2000)
            ->get();

        $export = $exportRows->handle($questions);
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $filename = 'cau-hoi-'.now()->format('Ymd-His').'.'.$format;

        Auditor::record(
            AuditAction::QuestionExported,
            $this->actor(),
            metadata: [
                'count' => $questions->count(),
                'format' => $format,
                'filters' => $request->except(['format']),
            ],
        );

        return response()->streamDownload(function () use ($spreadsheet, $format, $export): void {
            if ($format === 'csv') {
                echo $spreadsheet->csvString($export['headers'], $export['rows']);

                return;
            }

            echo $spreadsheet->xlsxBinary($export['headers'], $export['rows']);
        }, $filename, [
            'Content-Type' => $format === 'csv'
                ? 'text/csv; charset=UTF-8'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
