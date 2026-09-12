<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Modules\QuestionBank\Enums\QuestionImportBatchStatus;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionImportBatch;
use Modules\QuestionBank\Support\QuestionImportSchema;
use Modules\QuestionBank\Support\QuestionSpreadsheet;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class QuestionImportExportTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        $this->lesson = $this->makeMedicalNode([
            'name' => 'Tim mạch',
            'slug' => 'tim-mach-admin-test',
            'sort_order' => 1,
        ]);
    }

    public function test_editor_can_download_import_template(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        $this->actingAsStaff($editor)
            ->get(route('admin.questions.import.template', ['format' => 'xlsx']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAsStaff($editor)
            ->get(route('admin.questions.import.template', ['format' => 'csv']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_admin_cannot_open_import_wizard(): void
    {
        $admin = $this->staffUser(Role::Admin);

        $this->actingAsStaff($admin)
            ->get(route('admin.questions.import'))
            ->assertForbidden();
    }

    public function test_editor_import_creates_drafts_only_and_ignores_publish_columns(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $file = $this->csvUpload([
            array_merge(QuestionImportSchema::headers(), ['status', 'publisher_id']),
            array_merge($this->validRow(), ['published', '99']),
        ]);

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.import.upload'), ['file' => $file])
            ->assertRedirect();

        $batch = QuestionImportBatch::query()->firstOrFail();

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.import.map', $batch), [
                'column_map' => QuestionImportSchema::autoMap(array_merge(
                    QuestionImportSchema::headers(),
                    ['status', 'publisher_id'],
                )),
            ])
            ->assertRedirect(route('admin.questions.import.show', $batch));

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.import.commit', $batch))
            ->assertRedirect(route('admin.questions.import.show', $batch))
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, $batch->original_filename));

        $this->actingAsStaff($editor)
            ->get(route('admin.questions.import.show', $batch))
            ->assertOk()
            ->assertSee($batch->original_filename, false)
            ->assertSee('Đã ghi bản nháp', false);

        $this->actingAsStaff($editor)
            ->get(route('admin.questions.index', ['import_batch_id' => $batch->getKey()]))
            ->assertOk()
            ->assertSee($batch->original_filename, false)
            ->assertSee('Bệnh nhân 55 tuổi đau ngực', false);

        $question = Question::query()->firstOrFail();
        $this->assertSame(QuestionStatus::Draft, $question->status);
        $this->assertSame(0, $question->version);
        $this->assertNull($question->published_version);
        $this->assertNull($question->publisher_id);
        $this->assertSame($batch->getKey(), $question->import_batch_id);
        $this->assertSame($editor->id, $question->created_by);
        $this->assertSame([$this->lesson->id], $question->lessons()->pluck('lessons.id')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.question.import']);
    }

    public function test_import_skips_invalid_rows_and_keeps_valid_as_draft(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $invalid = $this->validRow();
        $invalid[array_search('correct', QuestionImportSchema::headers(), true)] = '';

        $file = $this->csvUpload([
            QuestionImportSchema::headers(),
            $this->validRow('Câu hợp lệ để import.'),
            $invalid,
        ]);

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.import.upload'), ['file' => $file])
            ->assertRedirect();

        $batch = QuestionImportBatch::query()->firstOrFail();
        $this->actingAsStaff($editor)
            ->post(route('admin.questions.import.map', $batch), [
                'column_map' => QuestionImportSchema::autoMap(QuestionImportSchema::headers()),
            ])
            ->assertRedirect();

        $this->actingAsStaff($editor)
            ->get(route('admin.questions.import.show', $batch))
            ->assertOk()
            ->assertSee('hợp lệ')
            ->assertSee($batch->original_filename, false)
            ->assertSee('Đáp án đúng phải là một chữ');

        $this->assertSame(1, (int) $batch->fresh()->stats['valid']);
        $this->assertSame(1, (int) $batch->fresh()->stats['invalid']);

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.import.commit', $batch))
            ->assertRedirect();

        $this->assertSame(1, Question::query()->count());
        $this->assertSame(QuestionStatus::Draft, Question::query()->firstOrFail()->status);
        $this->assertSame(1, (int) $batch->fresh()->stats['created']);
    }

    public function test_import_with_existing_code_updates_question(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $this->actingAsStaff($editor)
            ->post(route('admin.questions.store'), [
                'stem' => 'Đề bài gốc trước khi import.',
                'difficulty' => 'medium',
                'lesson_ids' => [$this->lesson->id],
                'is_free' => '0',
                'options' => [
                    ['content' => 'Đúng', 'is_correct' => '1', 'explanation' => 'OK'],
                    ['content' => 'Sai', 'is_correct' => '0'],
                ],
            ])
            ->assertRedirect();

        $question = Question::query()->firstOrFail();
        $file = $this->csvUpload([
            QuestionImportSchema::headers(),
            $this->validRow('Đề bài đã cập nhật qua import.', $question->code),
        ]);

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.import.upload'), ['file' => $file])
            ->assertRedirect();

        $batch = QuestionImportBatch::query()->firstOrFail();
        $this->actingAsStaff($editor)
            ->post(route('admin.questions.import.map', $batch), [
                'column_map' => QuestionImportSchema::autoMap(QuestionImportSchema::headers()),
            ])
            ->assertRedirect();

        $this->actingAsStaff($editor)
            ->get(route('admin.questions.import.show', $batch))
            ->assertOk()
            ->assertSee('Cập nhật', false)
            ->assertSee($question->code, false);

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.import.commit', $batch))
            ->assertRedirect();

        $this->assertSame(1, Question::query()->count());
        $updated = $question->fresh();
        $this->assertSame($question->getKey(), $updated->getKey());
        $this->assertSame($question->code, $updated->code);
        $this->assertStringContainsString('Đề bài đã cập nhật qua import.', strip_tags((string) $updated->stem));
        $this->assertSame(QuestionStatus::Draft, $updated->status);
        $this->assertSame(0, (int) $batch->fresh()->stats['created']);
        $this->assertSame(1, (int) $batch->fresh()->stats['updated']);
    }

    public function test_import_update_of_in_review_question_appears_on_batch_list(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $this->actingAsStaff($editor)
            ->post(route('admin.questions.store'), [
                'stem' => 'Câu đang chờ duyệt trước import.',
                'difficulty' => 'medium',
                'lesson_ids' => [$this->lesson->id],
                'is_free' => '0',
                'options' => [
                    ['content' => 'Đúng', 'is_correct' => '1', 'explanation' => 'OK'],
                    ['content' => 'Sai', 'is_correct' => '0'],
                ],
            ])
            ->assertRedirect();

        $question = Question::query()->firstOrFail();
        $question->forceFill(['status' => QuestionStatus::InReview])->save();

        $file = $this->csvUpload([
            QuestionImportSchema::headers(),
            $this->validRow('Câu đã import đè khi đang duyệt.', $question->code),
        ]);

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.import.upload'), ['file' => $file])
            ->assertRedirect();

        $batch = QuestionImportBatch::query()->firstOrFail();
        $this->actingAsStaff($editor)
            ->post(route('admin.questions.import.map', $batch), [
                'column_map' => QuestionImportSchema::autoMap(QuestionImportSchema::headers()),
            ])
            ->assertRedirect();

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.import.commit', $batch))
            ->assertRedirect();

        $this->assertSame(QuestionStatus::Draft, $question->fresh()->status);

        $this->actingAsStaff($editor)
            ->get(route('admin.questions.index', ['import_batch_id' => $batch->getKey()]))
            ->assertOk()
            ->assertSee('Câu đã import đè khi đang duyệt.', false)
            ->assertSee($question->code, false);
    }

    public function test_import_with_unknown_code_is_rejected_and_reports_code(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $file = $this->csvUpload([
            QuestionImportSchema::headers(),
            $this->validRow('Câu có mã không tồn tại.', 'Q99999'),
            $this->validRow('Câu hợp lệ nhưng cùng lô có mã sai.'),
        ]);

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.import.upload'), ['file' => $file])
            ->assertRedirect();

        $batch = QuestionImportBatch::query()->firstOrFail();
        $this->actingAsStaff($editor)
            ->post(route('admin.questions.import.map', $batch), [
                'column_map' => QuestionImportSchema::autoMap(QuestionImportSchema::headers()),
            ])
            ->assertRedirect();

        $this->actingAsStaff($editor)
            ->get(route('admin.questions.import.show', $batch))
            ->assertOk()
            ->assertSee('Không tìm thấy mã', false)
            ->assertSee('Q99999', false);

        $this->assertSame(['Q99999'], $batch->fresh()->stats['invalid_codes'] ?? []);

        $this->actingAsStaff($editor)
            ->from(route('admin.questions.import.show', $batch))
            ->post(route('admin.questions.import.commit', $batch))
            ->assertRedirect(route('admin.questions.import.show', $batch))
            ->assertSessionHasErrors('batch');

        $this->assertSame(0, Question::query()->count());
        $this->assertNotSame(QuestionImportBatchStatus::Done, $batch->fresh()->status);
    }

    public function test_editor_can_export_filtered_questions_as_csv(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $this->actingAsStaff($editor)
            ->post(route('admin.questions.store'), [
                'stem' => 'Câu để xuất khẩu CSV.',
                'difficulty' => 'medium',
                'lesson_ids' => [$this->lesson->id],
                'is_free' => '0',
                'options' => [
                    ['content' => 'Đúng', 'is_correct' => '1', 'explanation' => 'OK'],
                    ['content' => 'Sai', 'is_correct' => '0'],
                ],
            ])
            ->assertRedirect();

        $response = $this->actingAsStaff($editor)
            ->get(route('admin.questions.export', ['format' => 'csv', 'status' => 'draft']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $content = $response->streamedContent();
        $question = Question::query()->firstOrFail();
        $this->assertStringContainsString('Câu để xuất khẩu CSV.', $content);
        $this->assertStringContainsString($question->code, $content);
        $this->assertStringNotContainsString($question->getKey(), $content);

        $headerLine = strtok(ltrim($content, "\xEF\xBB\xBF"), "\n");
        $this->assertIsString($headerLine);
        $this->assertNotContains('id', str_getcsv($headerLine));
        $this->assertContains('code', str_getcsv($headerLine));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.question.export']);
    }

    public function test_student_cannot_export_admin_questions(): void
    {
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $this->actingAs($student)
            ->get(route('admin.questions.export'))
            ->assertForbidden();
    }

    /**
     * @return list<string>
     */
    private function validRow(
        string $stem = 'Bệnh nhân 55 tuổi đau ngực. Chẩn đoán nào phù hợp nhất?',
        string $code = '',
    ): array {
        $row = array_fill_keys(QuestionImportSchema::headers(), '');
        $row['code'] = $code;
        $row['stem'] = $stem;
        $row['option_a'] = 'ACS';
        $row['option_b'] = 'GERD';
        $row['option_c'] = 'Lo lắng';
        $row['option_d'] = 'Viêm phổi';
        $row['option_a_explanation'] = 'Đúng';
        $row['correct'] = 'A';
        $row['explanation'] = 'Đúng';
        $row['difficulty'] = 'medium';
        $row['lesson_slugs'] = $this->lesson->slug;
        $row['is_free'] = '0';
        $row['exam_flag'] = '0';

        return array_values($row);
    }

    /**
     * @param  list<list<string>>  $table
     */
    private function csvUpload(array $table): UploadedFile
    {
        $headers = array_shift($table);
        $path = sys_get_temp_dir().'/qbank-import-test-'.uniqid().'.csv';
        app(QuestionSpreadsheet::class)->writeCsv($path, $headers, $table);

        return new UploadedFile($path, 'questions.csv', 'text/csv', null, true);
    }

    private function staffUser(Role $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        TwoFactorSecret::query()->create([
            'user_id' => $user->id,
            'secret' => (new TotpService)->generateSecret(),
            'recovery_codes' => [Hash::make('ABCD1234')],
            'confirmed_at' => now(),
        ]);

        return $user;
    }

    private function actingAsStaff(User $user): static
    {
        return $this->actingAs($user)->withSession([
            TwoFactorSession::KEY => now()->timestamp,
        ]);
    }
}
