<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Jobs\RecordAuditLogJob;
use App\Models\User;
use App\Models\UserActivitySession;
use App\Support\Audit\AuditContext;
use App\Support\Audit\Enums\AuditAction as PlatformAuditAction;
use App\Support\Audit\Enums\AuditCategory;
use App\Support\Audit\Enums\AuditPortal;
use App\Support\Audit\Enums\AuditResult;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use App\Support\Enums\UserStatus;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use LogicException;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Models\AuditLog;
use Modules\Admin\Support\Auditor;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Modules\QuestionBank\Models\Question;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class AdminAuditArchitectureTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        config()->set('audit.queue_enabled', false);
    }

    public function test_user_changes_store_standard_snapshots_metadata_and_relations(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $student = User::factory()->create(['status' => UserStatus::Active]);
        $student->assignRole(Role::Student->value);

        $this->actingAsStaff($admin)
            ->patch(route('admin.users.status', $student), [
                'status' => UserStatus::Suspended->value,
                'reason' => 'Kiểm tra audit',
            ])
            ->assertRedirect();

        $log = AuditLog::query()
            ->where('action', AuditAction::UserStatusChanged->value)
            ->sole();

        $this->assertSame(UserStatus::Active->value, $log->before['status']);
        $this->assertSame(UserStatus::Suspended->value, $log->after['status']);
        $this->assertSame([Role::Student->value], $log->after['roles']);
        $this->assertArrayNotHasKey('name', $log->after);
        $this->assertArrayNotHasKey('email', $log->after);
        $this->assertSame('Kiểm tra audit', $log->metadata['reason']);
        $this->assertTrue($student->auditLogs()->whereKey($log->id)->exists());
        $this->assertTrue($admin->performedAuditLogs()->whereKey($log->id)->exists());
    }

    public function test_question_create_audit_contains_normalized_content_and_review_context(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $topic = $this->makeMedicalNode([
            'name' => 'Hô hấp',
            'slug' => 'ho-hap-audit-test',
            'node_type' => 'specialty',
            'sort_order' => 1,
        ]);

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.store'), [
                'stem' => '<p>Ca lâm sàng audit</p>',
                'explanation' => '<p>Giải thích audit</p><img src="/storage/questions/audit.png" alt="ECG">',
                'attending_tip' => '<p>Gợi ý audit</p>',
                'difficulty' => 'medium',
                'medical_taxonomy_node_ids' => [$topic->id],
                'hints' => [
                    ['content' => '<p>Dấu hiệu gợi ý</p>', 'sort_order' => 0],
                ],
                'is_free' => '1',
                'exam_flag' => '1',
                'options' => [
                    ['content' => '<p>Đáp án đúng</p>', 'is_correct' => '1', 'explanation' => '<p>Vì sao đúng</p>'],
                    ['content' => '<p>Đáp án sai</p>', 'is_correct' => '0'],
                ],
            ])
            ->assertRedirect();

        $question = Question::query()->sole();
        $log = AuditLog::query()
            ->where('action', AuditAction::QuestionCreated->value)
            ->sole();

        $this->assertNull($log->before);
        $this->assertSame('Ca lâm sàng audit', strip_tags($log->after['stem']));
        $this->assertSame('Vì sao đúng', strip_tags($log->after['explanation']));
        $this->assertSame([$topic->id], $log->after['medical_taxonomy_node_ids']);
        $this->assertSame('Dấu hiệu gợi ý', strip_tags($log->after['hints'][0]['content']));
        $this->assertTrue($log->after['exam_flag']);
        $this->assertCount(2, $log->after['options']);
        $this->assertTrue($log->after['options'][0]['is_correct']);
        $this->assertSame('create', $log->metadata['review_action']);
        $this->assertNotNull($log->metadata['review_request_id']);
        $this->assertTrue($question->auditLogs()->whereKey($log->id)->exists());
    }

    public function test_auditor_redacts_sensitive_values_recursively(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $student = User::factory()->create();

        $log = Auditor::record(
            'test.audit.redaction',
            $admin,
            $student,
            [
                'password' => 'plain-secret',
                'nested' => [
                    'api_token' => 'token-value',
                    'email' => 'private@example.com',
                    'safe' => 'kept',
                ],
            ],
        );

        $this->assertSame('[REDACTED]', $log->before['password']);
        $this->assertSame('[REDACTED]', $log->before['nested']['api_token']);
        $this->assertSame('[REDACTED]', $log->before['nested']['email']);
        $this->assertSame('kept', $log->before['nested']['safe']);
        $this->assertStringNotContainsString('plain-secret', $log->getRawOriginal('before'));
        $this->assertStringNotContainsString('token-value', $log->getRawOriginal('before'));
        $this->assertStringNotContainsString('private@example.com', $log->getRawOriginal('before'));
    }

    public function test_auditor_records_normalized_device_os_and_browser_context(): void
    {
        $student = $this->staffUser(Role::Student);
        $request = Request::create('/dashboard', 'GET', server: [
            'REMOTE_ADDR' => '192.168.10.15',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
        ]);
        $request->setUserResolver(fn (): User => $student);

        $log = Auditor::record(
            PlatformAuditAction::LearningSessionCompleted,
            request: $request,
        );

        $this->assertSame($student->id, $log->actor_id);
        $this->assertSame('192.168.10.15', $log->ip);
        $this->assertSame('mobile', $log->device_type);
        $this->assertSame('iPhone', $log->device_name);
        $this->assertSame('iOS 17.5', $log->operating_system);
        $this->assertSame('Safari 17.5', $log->browser);
    }

    public function test_low_risk_audit_is_queued_while_security_audit_remains_immediate(): void
    {
        Queue::fake();
        config()->set('audit.queue_enabled', true);
        config()->set('queue.default', 'redis');
        $student = $this->staffUser(Role::Student);

        $queued = Auditor::record(PlatformAuditAction::LearningSessionCompleted, $student, $student);
        $immediate = Auditor::record(PlatformAuditAction::AuthPasswordChanged, $student, $student);

        $this->assertNull($queued);
        $this->assertNotNull($immediate);
        $this->assertDatabaseHas('audit_logs', ['action' => PlatformAuditAction::AuthPasswordChanged->value]);
        $this->assertDatabaseMissing('audit_logs', ['action' => PlatformAuditAction::LearningSessionCompleted->value]);
        Queue::assertPushed(RecordAuditLogJob::class, fn (RecordAuditLogJob $job): bool => $job->attributes['action'] === PlatformAuditAction::LearningSessionCompleted->value
            && filled($job->attributes['event_id']));
    }

    public function test_audit_rows_cannot_be_changed_or_deleted_through_the_model(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $log = Auditor::record('test.audit.immutable', $admin, $admin);

        try {
            $log->forceFill(['action' => 'tampered'])->save();
            $this->fail('Updating an audit log should throw.');
        } catch (LogicException $exception) {
            $this->assertSame('Audit logs are immutable.', $exception->getMessage());
        }

        $log->refresh();

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_audit_can_be_filtered_by_user_or_question_subject(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Auditor::record(AuditAction::UserEmailVerified, $admin, $firstUser);
        Auditor::record(AuditAction::UserEmailVerified, $admin, $secondUser);

        $this->actingAsStaff($admin)
            ->get(route('admin.audit.index', [
                'subject_type' => 'user',
                'subject_id' => $firstUser->id,
            ]))
            ->assertOk()
            ->assertViewHas('logs', function ($logs) use ($firstUser): bool {
                return $logs->count() === 1
                    && (string) $logs->first()->auditable_id === (string) $firstUser->id
                    && ! $logs->first()->relationLoaded('auditable');
            })
            ->assertSee('Người dùng #'.$firstUser->id);
    }

    public function test_audit_can_be_filtered_by_actor_name_or_id(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $firstActor = $this->staffUser(Role::Instructor);
        $firstActor->forceFill(['name' => 'Giảng viên Nguyễn An'])->save();
        $secondActor = $this->staffUser(Role::ContentEditor);
        $secondActor->forceFill(['name' => 'Biên tập viên Trần Bình'])->save();

        $firstLog = Auditor::record(PlatformAuditAction::ClassroomLiveStarted, $firstActor, $firstActor);
        Auditor::record(AuditAction::QuestionCreated, $secondActor, $secondActor);
        $this->assertNotNull($firstLog);

        $this->actingAsStaff($admin)
            ->get(route('admin.audit.index', ['actor' => 'Nguyễn An']))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs): bool => $logs->count() === 1
                && $logs->first()->actor_id === $firstActor->id);

        $this->get(route('admin.audit.index', ['actor' => (string) $secondActor->id]))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs): bool => $logs->count() === 1
                && $logs->first()->actor_id === $secondActor->id);

        $this->get(route('admin.audit.index', ['action' => 'Nguyễn An']))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs): bool => $logs->count() === 0)
            ->assertViewHas('actionSuggestions', fn (array $suggestions): bool => collect($suggestions)
                ->contains(fn (array $item): bool => $item['value'] === PlatformAuditAction::ClassroomLiveStarted->value)
                && collect($suggestions)->doesntContain(fn (array $item): bool => $item['value'] === 'Giảng viên Nguyễn An'));

        $this->get(route('admin.audit.index', ['action' => 'Bắt đầu buổi trực tiếp']))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs): bool => $logs->count() === 1
                && $logs->first()->id === $firstLog->id);

    }

    public function test_admin_can_view_instructor_and_student_activity_context(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $instructor = $this->staffUser(Role::Instructor);
        $student = $this->staffUser(Role::Student);

        Auditor::record(
            PlatformAuditAction::ClassroomLiveStarted,
            $instructor,
            $instructor,
            metadata: ['live_session_id' => 'live-15'],
            context: new AuditContext(
                portal: AuditPortal::Teach,
                category: AuditCategory::Classroom,
                sessionId: 'live-15',
            ),
        );
        Auditor::record(
            PlatformAuditAction::LearningSessionCompleted,
            $student,
            $student,
            metadata: ['question_session_id' => 'study-20'],
            context: new AuditContext(
                portal: AuditPortal::Student,
                category: AuditCategory::Learning,
                result: AuditResult::Success,
                sessionId: 'study-20',
            ),
        );
        foreach (PlatformAuditAction::hiddenLearningDetailValues() as $hiddenAction) {
            Auditor::record(
                $hiddenAction,
                $student,
                $student,
                metadata: ['question_session_id' => 'study-20'],
                context: new AuditContext(
                    portal: AuditPortal::Student,
                    category: AuditCategory::Learning,
                    sessionId: 'study-20',
                ),
            );
        }

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $instructor->id,
            'actor_role' => Role::Instructor->value,
            'portal' => AuditPortal::Teach->value,
            'category' => AuditCategory::Classroom->value,
            'result' => AuditResult::Success->value,
            'session_id' => 'live-15',
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.audit.index', [
                'actor_role' => Role::Instructor->value,
            ]))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs): bool => $logs->count() === 1
                && $logs->first()->action === PlatformAuditAction::ClassroomLiveStarted->value)
            ->assertSee('Bắt đầu buổi trực tiếp');

        $this->actingAsStaff($admin)
            ->get(route('admin.audit.index', ['related_user_id' => $student->id]))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs): bool => $logs->contains(
                fn (AuditLog $log): bool => $log->action === PlatformAuditAction::LearningSessionCompleted->value,
            ) && ! $logs->contains(
                fn (AuditLog $log): bool => in_array($log->action, PlatformAuditAction::hiddenLearningDetailValues(), true),
            ));

        $this->actingAsStaff($admin)
            ->get(route('admin.users.show', $student))
            ->assertOk()
            ->assertSee('Hoạt động gần đây của người dùng')
            ->assertDontSee(PlatformAuditAction::LearningSessionCompleted->value)
            ->assertDontSee(PlatformAuditAction::LearningQuestionAnswered->value)
            ->assertDontSee(PlatformAuditAction::LearningSessionPaused->value)
            ->assertDontSee(PlatformAuditAction::LearningSessionResumed->value)
            ->assertDontSee(PlatformAuditAction::LearningSessionDeleted->value);
    }

    public function test_user_detail_shows_recent_activity_sessions_instead_of_audit_logs(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $student = $this->staffUser(Role::Student);

        UserActivitySession::query()->create([
            'user_id' => $student->id,
            'session_id' => fake()->uuid(),
            'area' => '/qbank/session/{id}',
            'portal' => 'student',
            'started_at' => now()->subMinutes(5),
            'last_seen_at' => now(),
            'duration_seconds' => 0,
            'heartbeat_count' => 1,
            'ip' => '127.0.0.1',
            'device_type' => 'desktop',
            'device_name' => 'Mac',
            'operating_system' => 'macOS',
            'browser' => 'Chrome',
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.users.show', $student))
            ->assertOk()
            ->assertViewHas('activities', fn ($activities): bool => $activities->count() === 1)
            ->assertSee('Hoạt động gần đây')
            ->assertSee('Đã mở phiên làm bài QBank')
            ->assertDontSee('trong khoảng')
            ->assertSee('Cổng học viên')
            ->assertSee('Chrome trên macOS')
            ->assertSee('IP 127.0.0.1')
            ->assertDontSee('/qbank/session')
            ->assertDontSee('Thời lượng')
            ->assertDontSee('Audit gần đây');
    }

    public function test_instructor_and_student_cannot_open_admin_audit(): void
    {
        foreach ([Role::Instructor, Role::Student] as $role) {
            $this->actingAsStaff($this->staffUser($role))
                ->get(route('admin.audit.index'))
                ->assertForbidden();
        }
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
