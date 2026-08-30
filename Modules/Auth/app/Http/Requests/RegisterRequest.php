<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Modules\Auth\Data\RegisterData;
use Modules\Partner\Support\PartnerInviteIntent;

class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
            'invite_code' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * Store one canonical form of the email so the unique check, the login
     * lockout key and future lookups all agree.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
            'name' => Str::squish((string) $this->input('name')),
        ]);
    }

    public function toData(): RegisterData
    {
        $inviteFromField = filled($this->input('invite_code'));
        $inviteCode = PartnerInviteIntent::resolveForRegistration($this);

        return new RegisterData(
            name: (string) $this->string('name'),
            email: (string) $this->string('email'),
            password: (string) $this->string('password'),
            inviteCode: $inviteCode,
            inviteFromField: $inviteFromField,
        );
    }
}
