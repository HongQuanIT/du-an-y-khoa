<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SettingController extends Controller
{
    public function index(SettingService $settings): View
    {
        $this->authorizeSystemSettings();

        return view('admin::settings.index', [
            'groups' => $this->groups(),
            'settings' => $settings->all(),
        ]);
    }

    public function update(Request $request, SettingService $settings): RedirectResponse
    {
        $this->authorizeSystemSettings();

        $validated = $request->validate($this->rules());
        $updates = [];

        foreach ($this->groups() as $groupKey => $group) {
            foreach ($group['fields'] as $fieldKey => $field) {
                $inputKey = "settings.{$groupKey}.{$fieldKey}";
                $type = $field['type'];

                $value = match ($type) {
                    'boolean' => $request->boolean($inputKey),
                    'integer' => (int) data_get($validated, $inputKey, $field['default'] ?? 0),
                    default => data_get($validated, $inputKey),
                };

                $updates["{$groupKey}.{$fieldKey}"] = [
                    'value' => $value,
                    'type' => $type,
                ];
            }
        }

        $settings->updateMany($updates);

        return back()->with('status', 'Đã lưu cài đặt hệ thống.');
    }

    /**
     * @return array<string, array{label: string, description: string, icon: string, fields: array<string, array<string, mixed>>}>
     */
    private function groups(): array
    {
        return [
            'general' => [
                'label' => 'Cấu hình chung',
                'description' => 'Thông tin nhận diện và liên hệ hiển thị trên hệ thống.',
                'icon' => 'tune',
                'fields' => [
                    'site_name' => [
                        'label' => 'Tên hệ thống',
                        'type' => 'string',
                        'default' => config('app.name'),
                        'rules' => ['required', 'string', 'max:120'],
                    ],
                    'support_email' => [
                        'label' => 'Email hỗ trợ',
                        'type' => 'string',
                        'default' => '',
                        'rules' => ['nullable', 'email', 'max:160'],
                    ],
                    'support_hotline' => [
                        'label' => 'Hotline hỗ trợ',
                        'type' => 'string',
                        'default' => '',
                        'rules' => ['nullable', 'string', 'max:80'],
                    ],
                    'fanpage_url' => [
                        'label' => 'Link Fanpage',
                        'type' => 'string',
                        'default' => '',
                        'rules' => ['nullable', 'url', 'max:255'],
                    ],
                    'zalo_url' => [
                        'label' => 'Link Zalo',
                        'type' => 'string',
                        'default' => '',
                        'rules' => ['nullable', 'url', 'max:255'],
                    ],
                    'seo_description' => [
                        'label' => 'Tagline / SEO Meta Description',
                        'type' => 'string',
                        'default' => '',
                        'rules' => ['nullable', 'string', 'max:500'],
                        'textarea' => true,
                    ],
                ],
            ],
            'features' => [
                'label' => 'Tính năng',
                'description' => 'Bật tắt các hành vi vận hành có ảnh hưởng tới người học.',
                'icon' => 'toggle_on',
                'fields' => [
                    'registration_enabled' => [
                        'label' => 'Cho phép đăng ký tự do',
                        'type' => 'boolean',
                        'default' => true,
                        'rules' => ['sometimes', 'boolean'],
                    ],
                    'maintenance_mode' => [
                        'label' => 'Chế độ bảo trì',
                        'type' => 'boolean',
                        'default' => false,
                        'rules' => ['sometimes', 'boolean'],
                    ],
                    'free_test_question_limit' => [
                        'label' => 'Số câu hỏi mặc định cho bài test Free',
                        'type' => 'integer',
                        'default' => 20,
                        'min' => 1,
                        'max' => 500,
                        'rules' => ['required', 'integer', 'min:1', 'max:500'],
                    ],
                ],
            ],
            'integrations' => [
                'label' => 'Kết nối',
                'description' => 'Thông tin tích hợp dịch vụ ngoài, chuẩn bị cho Classroom và thông báo.',
                'icon' => 'hub',
                'fields' => [
                    'livekit_url' => [
                        'label' => 'LiveKit URL',
                        'type' => 'string',
                        'default' => '',
                        'rules' => ['nullable', 'url', 'max:255'],
                    ],
                    'livekit_api_key' => [
                        'label' => 'LiveKit API Key',
                        'type' => 'string',
                        'default' => '',
                        'rules' => ['nullable', 'string', 'max:255'],
                    ],
                    'notification_webhook_url' => [
                        'label' => 'Webhook Telegram/Slack',
                        'type' => 'string',
                        'default' => '',
                        'rules' => ['nullable', 'url', 'max:255'],
                    ],
                ],
            ],
            'reports' => [
                'label' => 'Báo cáo',
                'description' => 'Chu kỳ cron tạo snapshot cache báo cáo admin. Có thể làm mới thủ công bất cứ lúc nào trên trang báo cáo.',
                'icon' => 'analytics',
                'fields' => [
                    'cache_warm_interval_days' => [
                        'label' => 'Chu kỳ warm cache báo cáo (ngày)',
                        'help' => 'Mặc định 1 ngày. Cron kiểm tra mỗi giờ; chỉ chạy khi đã đủ số ngày kể từ lần warm trước.',
                        'type' => 'integer',
                        'default' => 1,
                        'min' => 1,
                        'max' => 30,
                        'options' => [
                            1 => 'Mỗi 1 ngày (mặc định)',
                            2 => 'Mỗi 2 ngày',
                            3 => 'Mỗi 3 ngày',
                            7 => 'Mỗi 7 ngày',
                            14 => 'Mỗi 14 ngày',
                            30 => 'Mỗi 30 ngày',
                        ],
                        'rules' => ['required', 'integer', 'min:1', 'max:30'],
                    ],
                ],
            ],
            'partner' => [
                'label' => 'Cộng tác viên',
                'description' => 'Attribution, % hoa hồng mặc định, hạn mã mời và quy tắc ghi nhận doanh thu chia sẻ.',
                'icon' => 'handshake',
                'fields' => [
                    'attribution_window_days' => [
                        'label' => 'Cửa sổ giữ mã mời (ngày) — từ lúc click link đến khi đăng ký',
                        'type' => 'integer',
                        'default' => 7,
                        'min' => 1,
                        'max' => 365,
                        'rules' => ['required', 'integer', 'min:1', 'max:365'],
                    ],
                    'default_commission_rate_percent' => [
                        'label' => 'Hoa hồng mặc định cho CTV mới (%)',
                        'type' => 'integer',
                        'default' => 10,
                        'min' => 0,
                        'max' => 100,
                        'rules' => ['required', 'integer', 'min:0', 'max:100'],
                    ],
                    'default_invite_expires_days' => [
                        'label' => 'Hạn mã mời mặc định (ngày) — 0 = không tự hết hạn khi tạo mã',
                        'type' => 'integer',
                        'default' => 7,
                        'min' => 0,
                        'max' => 3650,
                        'rules' => ['required', 'integer', 'min:0', 'max:3650'],
                    ],
                    'default_invite_max_uses' => [
                        'label' => 'Lượt dùng mã mặc định — 0 = không giới hạn',
                        'type' => 'integer',
                        'default' => 0,
                        'min' => 0,
                        'max' => 1000000,
                        'rules' => ['required', 'integer', 'min:0', 'max:1000000'],
                    ],
                    'commission_on_renewals' => [
                        'label' => 'Tính hoa hồng khi gia hạn / mua lại',
                        'type' => 'boolean',
                        'default' => true,
                        'rules' => ['sometimes', 'boolean'],
                    ],
                    'first_payment_window_days' => [
                        'label' => 'Chỉ tính HH cho thanh toán trong N ngày sau đăng ký — 0 = không giới hạn',
                        'type' => 'integer',
                        'default' => 0,
                        'min' => 0,
                        'max' => 3650,
                        'rules' => ['required', 'integer', 'min:0', 'max:3650'],
                    ],
                    'allow_self_referral' => [
                        'label' => 'Cho phép CTV tự mời chính tài khoản của mình',
                        'type' => 'boolean',
                        'default' => false,
                        'rules' => ['sometimes', 'boolean'],
                    ],
                    'min_payout_cents' => [
                        'label' => 'Số tiền chi trả tối thiểu (₫) — 0 = không tối thiểu. VD: 100000 = 100.000 ₫',
                        'type' => 'integer',
                        'default' => 0,
                        'min' => 0,
                        'rules' => ['required', 'integer', 'min:0'],
                    ],
                    'overwrite_attribution' => [
                        'label' => 'Last-touch: mã click sau ghi đè mã click trước (tắt = first-touch)',
                        'type' => 'boolean',
                        'default' => false,
                        'rules' => ['sometimes', 'boolean'],
                    ],
                    'require_active_partner' => [
                        'label' => 'Chỉ gắn attribution / ghi HH khi CTV đang hoạt động',
                        'type' => 'boolean',
                        'default' => true,
                        'rules' => ['sometimes', 'boolean'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(): array
    {
        $rules = [];

        foreach ($this->groups() as $groupKey => $group) {
            foreach ($group['fields'] as $fieldKey => $field) {
                $rules["settings.{$groupKey}.{$fieldKey}"] = $field['rules'];
            }
        }

        return $rules;
    }

    private function authorizeSystemSettings(): void
    {
        abort_unless(auth()->user()?->can(Permission::SystemManage->value), 403);
    }
}
