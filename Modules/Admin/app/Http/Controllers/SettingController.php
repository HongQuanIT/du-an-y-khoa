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
