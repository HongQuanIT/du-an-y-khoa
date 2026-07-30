<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Modules\Auth\Data\LoginData;

class LoginRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Emails are stored lowercase, so normalise before matching credentials
     * and before deriving the lockout key.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

    public function toData(): LoginData
    {
        return new LoginData(
            email: (string) $this->string('email'),
            password: (string) $this->string('password'),
            remember: $this->boolean('remember'),
            ip: $this->ip(),
        );
    }
}
