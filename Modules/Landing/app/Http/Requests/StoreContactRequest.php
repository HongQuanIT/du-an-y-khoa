<?php

declare(strict_types=1);

namespace Modules\Landing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Admin\Enums\ContactSubject;

final class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:filter', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[\d\s\-+().]{7,32}$/'],
            'subject' => ['required', 'string', Rule::enum(ContactSubject::class)],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'privacy' => ['accepted'],
            // Honeypot — bots that fill it get a silent fake success (see controller).
            'company_website' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập họ tên.',
            'name.min' => 'Họ tên cần ít nhất 2 ký tự.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
            'subject.required' => 'Vui lòng chọn chủ đề liên hệ.',
            'subject.enum' => 'Chủ đề liên hệ không hợp lệ.',
            'message.required' => 'Vui lòng nhập nội dung.',
            'message.min' => 'Nội dung cần ít nhất 10 ký tự.',
            'message.max' => 'Nội dung không được vượt quá 5000 ký tự.',
            'privacy.accepted' => 'Bạn cần đồng ý với chính sách bảo mật để gửi liên hệ.',
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     email: string,
     *     phone: string|null,
     *     subject: string,
     *     message: string,
     * }
     */
    public function payload(): array
    {
        $phone = trim((string) $this->input('phone', ''));

        return [
            'name' => (string) $this->string('name'),
            'email' => (string) $this->string('email'),
            'phone' => $phone !== '' ? $phone : null,
            'subject' => (string) $this->string('subject'),
            'message' => (string) $this->string('message'),
        ];
    }

    public function isHoneypotTriggered(): bool
    {
        return filled($this->input('company_website'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'phone' => trim((string) $this->input('phone', '')),
            'message' => trim((string) $this->input('message')),
        ]);
    }
}
