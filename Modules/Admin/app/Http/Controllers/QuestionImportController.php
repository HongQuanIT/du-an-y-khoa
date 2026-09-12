<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Admin\Actions\CommitQuestionImportAction;
use Modules\Admin\Actions\PrepareQuestionImportPreviewAction;
use Modules\QuestionBank\Enums\QuestionImportBatchStatus;
use Modules\QuestionBank\Models\QuestionImportBatch;
use Modules\QuestionBank\Support\QuestionImportSchema;
use Modules\QuestionBank\Support\QuestionSpreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class QuestionImportController extends Controller
{
    public function create(): View
    {
        $this->authorizePermission(Permission::QuestionCreate);

        return view('admin::questions.import', [
            'step' => 'upload',
            'batch' => null,
            'fields' => QuestionImportSchema::fields(),
            'preview' => null,
            'templateCsvUrl' => route('admin.questions.import.template', ['format' => 'csv']),
            'templateXlsxUrl' => route('admin.questions.import.template', ['format' => 'xlsx']),
        ]);
    }

    public function template(Request $request, QuestionSpreadsheet $spreadsheet): StreamedResponse
    {
        $this->authorizePermission(Permission::QuestionCreate);

        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $headers = QuestionImportSchema::headers();
        $rows = [QuestionImportSchema::sampleRow()];

        $filename = 'mau-import-cau-hoi.'.$format;

        return response()->streamDownload(function () use ($spreadsheet, $format, $headers, $rows): void {
            if ($format === 'csv') {
                echo $spreadsheet->csvString($headers, $rows);

                return;
            }

            echo $spreadsheet->xlsxBinary($headers, $rows, QuestionImportSchema::guideRows());
        }, $filename, [
            'Content-Type' => $format === 'csv'
                ? 'text/csv; charset=UTF-8'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function store(Request $request, QuestionSpreadsheet $spreadsheet): RedirectResponse
    {
        $this->authorizePermission(Permission::QuestionCreate);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:csv,txt,xlsx'],
        ], [
            'file.required' => 'Vui lòng chọn tệp Excel hoặc CSV.',
            'file.mimes' => 'Chỉ nhận tệp .xlsx hoặc .csv.',
            'file.max' => 'Tệp tối đa 5MB.',
        ]);

        $file = $data['file'];
        $extension = strtolower($file->getClientOriginalExtension() ?: 'csv');
        $format = $extension === 'xlsx' ? 'xlsx' : 'csv';

        $batch = QuestionImportBatch::query()->create([
            'uploaded_by' => $this->actor()->getKey(),
            'original_filename' => $file->getClientOriginalName(),
            'disk_path' => '',
            'format' => $format,
            'status' => QuestionImportBatchStatus::Uploaded,
        ]);

        $relative = 'question-imports/'.$batch->getKey().'.'.$format;
        Storage::disk('local')->putFileAs('question-imports', $file, $batch->getKey().'.'.$format);

        try {
            $parsed = $spreadsheet->read(Storage::disk('local')->path($relative));
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($relative);
            $batch->delete();

            throw ValidationException::withMessages([
                'file' => $e->getMessage() ?: 'Không đọc được tệp.',
            ]);
        }

        if ($parsed['headers'] === [] || $parsed['rows'] === []) {
            Storage::disk('local')->delete($relative);
            $batch->delete();

            throw ValidationException::withMessages([
                'file' => 'Tệp không có dòng dữ liệu.',
            ]);
        }

        $batch->forceFill([
            'disk_path' => $relative,
            'source_headers' => $parsed['headers'],
            'column_map' => QuestionImportSchema::autoMap($parsed['headers']),
        ])->save();

        return redirect()->route('admin.questions.import.show', $batch);
    }

    public function show(
        Request $request,
        QuestionImportBatch $batch,
        PrepareQuestionImportPreviewAction $previewAction,
    ): View {
        $this->authorizePermission(Permission::QuestionCreate);
        $this->authorizeBatch($batch);

        $step = match ($batch->status) {
            QuestionImportBatchStatus::Done => 'done',
            QuestionImportBatchStatus::Validated, QuestionImportBatchStatus::Mapped => 'preview',
            default => 'map',
        };

        if ($request->boolean('remap') && $batch->status !== QuestionImportBatchStatus::Done) {
            $step = 'map';
        }

        $preview = null;
        if ($step === 'preview' && $batch->column_map) {
            try {
                $preview = $previewAction->handle($batch, $batch->column_map);
            } catch (ValidationException) {
                $step = 'map';
            }
        }

        return view('admin::questions.import', [
            'step' => $step,
            'batch' => $batch->fresh(),
            'fields' => QuestionImportSchema::fields(),
            'preview' => $preview,
            'templateCsvUrl' => route('admin.questions.import.template', ['format' => 'csv']),
            'templateXlsxUrl' => route('admin.questions.import.template', ['format' => 'xlsx']),
        ]);
    }

    public function map(
        Request $request,
        QuestionImportBatch $batch,
        PrepareQuestionImportPreviewAction $previewAction,
    ): RedirectResponse {
        $this->authorizePermission(Permission::QuestionCreate);
        $this->authorizeBatch($batch);
        abort_if($batch->status === QuestionImportBatchStatus::Done, 409, 'Lô này đã import xong.');

        $data = $request->validate([
            'column_map' => ['required', 'array'],
            'column_map.*' => ['nullable'],
        ]);

        $map = [];
        foreach ($data['column_map'] as $field => $index) {
            if ($index === null || $index === '') {
                continue;
            }
            $map[(string) $field] = (int) $index;
        }

        $batch->forceFill([
            'column_map' => $map,
            'status' => QuestionImportBatchStatus::Mapped,
        ])->save();

        $previewAction->handle($batch, $map);

        return redirect()->route('admin.questions.import.show', $batch);
    }

    public function commit(
        QuestionImportBatch $batch,
        CommitQuestionImportAction $action,
    ): RedirectResponse {
        $this->authorizePermission(Permission::QuestionCreate);
        $this->authorizeBatch($batch);

        $result = $action->handle($this->actor(), $batch);

        $parts = [];
        if ($result['created'] > 0) {
            $parts[] = 'tạo '.$result['created'].' câu mới';
        }
        if ($result['updated'] > 0) {
            $parts[] = 'cập nhật '.$result['updated'].' câu';
        }
        $summary = $parts !== [] ? implode(', ', $parts) : 'không ghi câu nào';

        $filename = $batch->original_filename ?: 'tệp đã tải';

        return redirect()
            ->route('admin.questions.import.show', $batch)
            ->with(
                'status',
                sprintf(
                    'Đã import từ tệp %s: %s. %s Các câu này chưa được xuất bản — hãy xem lại rồi gửi giảng viên duyệt.',
                    $filename,
                    $summary,
                    $result['skipped'] > 0 ? $result['skipped'].' dòng lỗi đã bỏ qua.' : '',
                ),
            );
    }

    public function errors(QuestionImportBatch $batch): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizePermission(Permission::QuestionCreate);
        $this->authorizeBatch($batch);
        abort_unless(filled($batch->error_report_path) && Storage::disk('local')->exists($batch->error_report_path), 404);

        return Storage::disk('local')->download(
            $batch->error_report_path,
            'import-loi-'.$batch->getKey().'.csv',
        );
    }

    private function authorizeBatch(QuestionImportBatch $batch): void
    {
        abort_unless((int) $batch->uploaded_by === (int) $this->actor()->getKey(), 404);
    }

    private function authorizePermission(Permission $permission): void
    {
        abort_unless($this->actor()->can($permission->value), 403);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
