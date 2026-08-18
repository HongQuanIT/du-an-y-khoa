<?php

declare(strict_types=1);

namespace Modules\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;
use Modules\Media\Support\ExternalUrlGuard;

final class RegisterExternalMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'url' => trim((string) $this->input('url', '')),
            'import' => $this->boolean('import'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048'],
            'alt' => ['nullable', 'string', 'max:255'],
            'import' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $url = (string) $this->input('url', '');

            if ($url === '') {
                return;
            }

            try {
                ExternalUrlGuard::assertHttpUrl($url);

                if ($this->boolean('import')) {
                    ExternalUrlGuard::assertSafeToFetch($url);
                }
            } catch (InvalidArgumentException $e) {
                $validator->errors()->add('url', $e->getMessage());
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.required' => 'Vui lòng dán URL ảnh.',
        ];
    }
}
