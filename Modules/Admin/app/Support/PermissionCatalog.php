<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Support\Enums\Permission as PermissionEnum;
use App\Support\Enums\PortalGroup;
use App\Support\Enums\Role as RoleEnum;
use App\Support\Rbac\PermissionRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Groups Spatie permissions by product portal for admin catalog / role matrix UI.
 */
final class PermissionCatalog
{
    private const HIDDEN_PERMISSION_NAMES = [
        'classroom.manage',
    ];

    public static function roleLabel(Role $role): string
    {
        $systemRole = RoleEnum::tryFrom($role->name);

        if ($systemRole !== null) {
            return $systemRole->label();
        }

        $displayName = trim((string) ($role->display_name ?? ''));

        return $displayName !== ''
            ? $displayName
            : Str::headline(str_replace(['_', '.', '-'], ' ', $role->name));
    }

    /**
     * @return array<string, array{
     *     portal: PortalGroup,
     *     permissions: Collection<int, Permission>,
     *     modules: list<array{key: string, label: string, count: int, resources: list<array{key: string, label: string, permissions: Collection<int, Permission>}>}>,
     * }>
     */
    public static function groupedByPortal(): array
    {
        $validNames = app(PermissionRegistry::class)->names();

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $validNames)
            ->whereNotIn('name', self::HIDDEN_PERMISSION_NAMES)
            ->orderBy('name')
            ->get();

        $grouped = [];

        foreach (PortalGroup::cases() as $portal) {
            $grouped[$portal->value] = [
                'portal' => $portal,
                'permissions' => collect(),
            ];
        }

        foreach ($permissions as $permission) {
            foreach (self::portalsForPermission($permission) as $portal) {
                $grouped[$portal->value]['permissions']->push($permission);
            }
        }

        foreach ($grouped as &$group) {
            $group['modules'] = self::modulesFor($group['permissions']);
        }
        unset($group);

        return $grouped;
    }

    /**
     * Priority weight for permission action within a resource:
     * 1. view (view, view_any)
     * 2. create
     * 3. update / edit
     * 4. delete
     * 5. import
     * 6. export
     * 7. approve
     * 8. other actions (stable alphabetically)
     */
    public static function actionPriority(string $permissionName): int
    {
        $action = explode('.', $permissionName, 2)[1] ?? $permissionName;

        return match ($action) {
            'view_any' => 10,
            'view' => 11,
            'create' => 20,
            'update', 'edit' => 30,
            'delete' => 40,
            'import' => 50,
            'export' => 60,
            'approve' => 70,
            'reject' => 75,
            'adjudicate' => 78,
            'publish' => 80,
            default => 100,
        };
    }

    /**
     * @param  Collection<int, Permission>  $permissions
     * @return list<array{key: string, label: string, count: int, resources: list<array{key: string, label: string, permissions: Collection<int, Permission>}>}>
     */
    private static function modulesFor(Collection $permissions): array
    {
        return $permissions
            ->groupBy(fn (Permission $permission): string => self::moduleKey($permission))
            ->map(function (Collection $modulePermissions, string $module): array {
                $resources = $modulePermissions
                    ->groupBy(fn (Permission $permission): string => self::resourceKey($permission))
                    ->map(fn (Collection $items, string $resource): array => [
                        'key' => $resource,
                        'label' => self::resourceLabel($resource),
                        'permissions' => $items->sortBy(fn (Permission $permission): array => [
                            self::actionPriority($permission->name),
                            $permission->name,
                        ])->values(),
                    ])
                    ->sortBy('label')
                    ->values()
                    ->all();

                return [
                    'key' => $module,
                    'label' => self::moduleLabel($module),
                    'count' => $modulePermissions->count(),
                    'resources' => $resources,
                ];
            })
            ->sortBy(fn (array $module): array => [self::moduleOrder($module['key']), $module['label']])
            ->values()
            ->all();
    }

    public static function actionLabel(string $permission): string
    {
        $action = explode('.', $permission, 2)[1] ?? $permission;

        return match ($action) {
            'view_any' => 'Xem toàn bộ dữ liệu',
            'view' => 'Xem',
            'create' => 'Tạo mới',
            'update', 'edit' => 'Chỉnh sửa',
            'delete' => 'Xóa',
            'manage' => 'Quản lý',
            'assign', 'role_assign', 'instructor_assign' => 'Phân công',
            'status_update' => 'Đổi trạng thái',
            'password_reset' => 'Đặt lại mật khẩu',
            'password_update' => 'Đổi mật khẩu',
            'avatar_update' => 'Đổi ảnh đại diện',
            'two_factor_toggle' => 'Bật / tắt 2FA',
            'two_factor_manage' => 'Quản lý / Đặt lại 2FA',
            'email_verify' => 'Xác minh email',
            'publish' => 'Xuất bản',
            'archive' => 'Lưu trữ',
            'export' => 'Xuất dữ liệu',
            'import' => 'Nhập dữ liệu',
            'toggle' => 'Bật / tắt',
            'approve' => 'Phê duyệt',
            'reject' => 'Từ chối',
            'adjudicate' => 'Đánh dấu QA duyệt',
            'start' => 'Bắt đầu',
            'end' => 'Kết thúc',
            'join' => 'Tham gia',
            'leave' => 'Rời khỏi',
            'send' => 'Gửi',
            'reply' => 'Phản hồi',
            'resolve' => 'Đóng xử lý',
            'upload' => 'Tải lên',
            'download' => 'Tải xuống',
            'repeat' => 'Làm lại',
            'submit' => 'Nộp bài / Gửi duyệt',
            'review' => 'Xem lại / Đánh giá',
            'schedule' => 'Lên lịch',
            'ban' => 'Cấm thành viên',
            'raise' => 'Giơ tay phát biểu',
            'mute' => 'Tắt tiếng chat',
            'present' => 'Trình bày',
            'clone' => 'Nhân bản',
            'replan' => 'Lập lại kế hoạch',
            'note' => 'Ghi chú',
            'flag' => 'Gắn cờ',
            'highlight' => 'Tô màu văn bản',
            'research' => 'Nghiên cứu',
            'complete' => 'Hoàn thành',
            'skip' => 'Bỏ qua',
            'take' => 'Làm bài thi',
            'use' => 'Sử dụng',
            'checkout' => 'Thanh toán',
            'portal' => 'Truy cập cổng',
            'close' => 'Đóng lớp',
            'reopen' => 'Mở lại lớp',
            'remove' => 'Xóa khỏi lớp',
            'invite' => 'Mời tham gia',
            'mark_paid' => 'Đánh dấu đã chi trả',
            'maintenance_toggle' => 'Bật/tắt bảo trì',
            'rollout' => 'Triển khai tính năng',
            'compare' => 'So sánh phiên bản',
            'restore' => 'Khôi phục',
            'retire' => 'Thu hồi',
            'reorder' => 'Sắp xếp thứ tự',
            'refresh' => 'Làm mới',
            'progress_view' => 'Xem tiến độ',
            'recommendation_view' => 'Xem gợi ý',
            'usage_view' => 'Xem lượt sử dụng',
            'question_search' => 'Tìm kiếm câu hỏi',
            'question_assign' => 'Gán câu hỏi',
            'result_view' => 'Xem kết quả',
            'result_export' => 'Xuất kết quả',
            'force_end' => 'Bắt buộc kết thúc',
            'create_on_behalf' => 'Tạo thay giảng viên',
            'live_moderate' => 'Điều phối phòng live',
            'revoke' => 'Thu hồi quyền / phiên',
            default => Str::headline($action),
        };
    }

    private static function moduleKey(Permission $permission): string
    {
        if (in_array($permission->name, ['question_flag.view', PermissionEnum::QuestionFlag->value], true)) {
            return 'question_flag';
        }

        $definition = app(PermissionRegistry::class)->find($permission->name);
        $module = $definition?->module ?? 'other';

        return match ($module) {
            'user' => 'user_management',
            'role', 'permission' => 'rbac',
            'question' => 'question_bank',
            'topic' => 'taxonomy',
            'audit', 'report' => 'reporting',
            'cms' => 'cms',
            'media' => 'media',
            'contact' => 'contact',
            'system', 'notification', 'support' => 'system',
            // Partner code/payout views are shared with the partner portal,
            // but on the Admin matrix they belong to one management module.
            'admin', 'partner', 'affiliate' => 'partner_management',
            'session', 'library', 'analytics' => 'learning',
            default => $module,
        };
    }

    private static function resourceKey(Permission $permission): string
    {
        return match ($permission->name) {
            PermissionEnum::QuestionFlag->value => 'question_flag',
            'question_flag.view' => 'question_flag',
            'question_version.view', 'question_version.restore' => 'question',
            'notification.delete' => 'notification_broadcast',
            default => explode('.', $permission->name, 2)[0],
        };
    }

    private static function moduleLabel(string $module): string
    {
        return match ($module) {
            'user_management' => 'Người dùng & hồ sơ',
            'rbac' => 'Vai trò & phân quyền',
            'question_bank' => 'Ngân hàng câu hỏi',
            'question_flag' => 'Gắn cờ câu hỏi',
            'taxonomy' => 'Danh mục y khoa',
            'exam' => 'Kỳ thi',
            'classroom' => 'Lớp học & Live',
            'cms' => 'CMS & Landing',
            'media' => 'Thư viện Media',
            'contact' => 'Liên hệ & góp ý',
            'content' => 'Nội dung & Media',
            'reporting' => 'Báo cáo & nhật ký',
            'billing', 'subscription' => 'Thanh toán & gói',
            'partner_management', 'affiliate', 'access' => 'Cộng tác viên',
            'system' => 'Hệ thống & hỗ trợ',
            'dashboard' => 'Tổng quan',
            'profile', 'account' => 'Tài khoản cá nhân',
            'review' => 'Kiểm duyệt',
            'notification' => 'Thông báo',
            'study_plan' => 'Kế hoạch học tập',
            'learning' => 'Hoạt động học tập',
            'ai' => 'Trợ lý AI',
            default => Str::headline($module),
        };
    }

    private static function resourceLabel(string $resource): string
    {
        return match ($resource) {
            'user' => 'Người dùng',
            'learner_profile' => 'Hồ sơ học viên',
            'user_session' => 'Phiên đăng nhập',
            'learner_catalog' => 'Dữ liệu học viên',
            'role' => 'Vai trò',
            'permission', 'role_permission' => 'Quyền hạn',
            'question', 'editor_question' => 'Câu hỏi',
            'question_flag' => 'Gắn cờ câu hỏi',
            'question_version' => 'Phiên bản câu hỏi',
            'question_feedback' => 'Phản hồi câu hỏi',
            'session' => 'Phiên luyện tập',
            'learning_tool' => 'Công cụ học tập',
            'taxonomy', 'editor_taxonomy' => 'Tổng quan phân loại',
            'blueprint', 'editor_blueprint' => 'Ma trận đề thi',
            'curriculum', 'editor_curriculum' => 'Danh mục kiến thức',
            'tag', 'editor_tag' => 'Thẻ tag',
            'classroom' => 'Lớp học',
            'classroom_settings' => 'Cài đặt lớp học',
            'classroom_member' => 'Thành viên lớp học',
            'classroom_session' => 'Lịch / Buổi học',
            'classroom_oversight' => 'Giám sát lớp học',
            'live_message' => 'Tin nhắn Live',
            'live_hand' => 'Giơ tay phát biểu',
            'live_chat' => 'Trò chuyện Live',
            'live_question' => 'Câu hỏi thảo luận Live',
            'live_moderation' => 'Điều phối phòng Live',
            'study_plan' => 'Kế hoạch học tập',
            'study_plan_task' => 'Nhiệm vụ học tập',
            'search' => 'Tìm kiếm',
            'bookmark' => 'Câu hỏi đã lưu',
            'exam' => 'Kỳ thi',
            'ai' => 'Trợ lý AI',
            'billing_plan' => 'Gói và bảng giá',
            'billing_price' => 'Mức giá',
            'billing_subscription' => 'Lịch sử subscription',
            'billing_payment' => 'Giao dịch thanh toán',
            'billing_gateway' => 'Cổng thanh toán',
            'invoice' => 'Hóa đơn',
            'subscription' => 'Gói thuê bao',
            'partner' => 'Đối tác',
            'partner_code' => 'Mã giới thiệu',
            'partner_commission' => 'Hoa hồng',
            'partner_referral' => 'Người được mời',
            'partner_payout' => 'Chi trả CTV',
            'report' => 'Báo cáo',
            'report_schedule' => 'Lịch báo cáo',
            'audit', 'audit_log', 'access_audit' => 'Audit log',
            'cms', 'cms_page', 'cms_menu', 'cms_banner', 'cms_faq' => 'CMS & Landing',
            'media', 'library', 'editor_media' => 'Thư viện Media',
            'contact' => 'Liên hệ & góp ý',
            'support', 'support_conversation' => 'Hỗ trợ trực tuyến',
            'system', 'system_setting' => 'Cài đặt hệ thống',
            'notification', 'notification_broadcast', 'teach_notification', 'editor_notification' => 'Thông báo',
            'profile', 'teach_profile', 'partner_profile', 'editor_profile' => 'Hồ sơ tài khoản',
            'teaching_dashboard', 'learner_dashboard', 'editor_dashboard' => 'Bảng điều khiển',
            default => Str::headline($resource),
        };
    }

    private static function moduleOrder(string $module): int
    {
        $order = ['dashboard', 'user_management', 'account', 'profile', 'rbac', 'question_bank', 'question_flag', 'taxonomy', 'study_plan', 'learning', 'exam', 'classroom', 'review', 'content', 'reporting', 'billing', 'subscription', 'partner_management', 'access', 'affiliate', 'notification', 'ai', 'system'];

        $position = array_search($module, $order, true);

        return $position === false ? 999 : $position;
    }

    public static function belongsToPortal(Permission $permission, PortalGroup $portal): bool
    {
        return in_array($portal, self::portalsForPermission($permission), true);
    }

    /** @return list<PortalGroup> */
    public static function portalsForPermission(Permission $permission): array
    {
        $definition = app(PermissionRegistry::class)->find($permission->name);
        if ($definition !== null) {
            return $definition->portals;
        }

        $stored = $permission->getAttribute('portals');
        if (is_string($stored) && $stored !== '') {
            try {
                $stored = json_decode($stored, true, flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $stored = [];
            }
        }

        $portals = collect(is_array($stored) ? $stored : [])
            ->map(fn ($value): ?PortalGroup => PortalGroup::tryFrom((string) $value))
            ->filter()
            ->values()
            ->all();

        if ($portals !== []) {
            return $portals;
        }

        $enum = PermissionEnum::tryFrom($permission->name);
        $portal = $enum?->portal()
            ?? PortalGroup::tryFrom((string) $permission->getAttribute('portal'))
            ?? PortalGroup::Admin;

        return [$portal];
    }

    /**
     * Role labels (excluding the permission's primary portal roles when only one)
     * that currently hold each permission — for “cũng dùng bởi” badges.
     *
     * @return array<string, list<string>> permission name => role labels
     */
    public static function roleLabelsByPermission(): array
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->get();

        $map = [];

        foreach ($roles as $role) {
            $label = self::roleLabel($role);

            foreach ($role->permissions as $permission) {
                $map[$permission->name][] = $label;
            }
        }

        foreach ($map as $name => $labels) {
            $map[$name] = array_values(array_unique($labels));
        }

        return $map;
    }

    /**
     * Roles keyed by portal for index UI.
     *
     * @param  Collection<int, Role>  $roles
     * @return array<string, array{portal: PortalGroup, roles: list<Role>}>
     */
    public static function rolesGroupedByPortal(Collection $roles): array
    {
        $byName = $roles->keyBy('name');
        $grouped = [];

        foreach (PortalGroup::cases() as $portal) {
            $portalRoles = [];

            foreach (RoleEnum::rolesIn($portal) as $enum) {
                $model = $byName->get($enum->value);
                if ($model !== null) {
                    $portalRoles[] = $model;
                    $byName->forget($enum->value);
                }
            }

            $grouped[$portal->value] = [
                'portal' => $portal,
                'roles' => $portalRoles,
            ];
        }

        // Custom roles persist their selected portal. Legacy records default to Admin.
        foreach ($byName->values() as $customRole) {
            $portal = PortalGroup::tryFrom((string) $customRole->portal) ?? PortalGroup::Admin;
            $grouped[$portal->value]['roles'][] = $customRole;
        }

        return $grouped;
    }
}
