<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserActivitySession extends Model
{
    protected $fillable = [
        'user_id', 'session_id', 'area', 'portal', 'started_at', 'last_seen_at',
        'duration_seconds', 'heartbeat_count', 'ip', 'device_type', 'device_name',
        'operating_system', 'browser',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'duration_seconds' => 'integer',
            'heartbeat_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activityLabel(): string
    {
        $area = '/'.ltrim((string) $this->area, '/');

        return match (true) {
            str_contains($area, '/live/') => 'Tham gia buổi học trực tiếp',
            str_starts_with($area, '/qbank/session/') => 'Làm bài trong ngân hàng câu hỏi',
            str_starts_with($area, '/qbank/review/'),
            str_contains($area, '/review') => 'Xem lại bài làm và đáp án',
            str_starts_with($area, '/qbank') => 'Học với ngân hàng câu hỏi',
            str_starts_with($area, '/exams/'),
            str_starts_with($area, '/exam/') => 'Làm bài thi',
            str_starts_with($area, '/classrooms/') => 'Xem và tham gia lớp học',
            str_starts_with($area, '/classrooms') => 'Khám phá các lớp học',
            str_starts_with($area, '/study-plan') => 'Xem và cập nhật kế hoạch học tập',
            str_starts_with($area, '/bookmarks'),
            str_starts_with($area, '/library') => 'Xem nội dung đã lưu',
            str_starts_with($area, '/flashcards') => 'Ôn tập bằng flashcard',
            str_starts_with($area, '/support') => 'Trao đổi với bộ phận hỗ trợ',
            str_starts_with($area, '/teach/classes') => 'Quản lý lớp giảng dạy',
            str_starts_with($area, '/teach') => 'Sử dụng cổng giảng viên',
            str_starts_with($area, '/admin') => 'Sử dụng trang quản trị',
            default => $this->portal === 'teach'
                ? 'Sử dụng cổng giảng viên'
                : ($this->portal === 'admin' ? 'Sử dụng trang quản trị' : 'Sử dụng trang học tập'),
        };
    }

    public function portalLabel(): string
    {
        return match ($this->portal) {
            'admin' => 'Admin',
            'teach' => 'Giảng viên',
            default => 'Học viên',
        };
    }
}
