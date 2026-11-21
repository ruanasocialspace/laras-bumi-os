<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class AccountRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower($this->input('email'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('manage', User::class);
    }

    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $emailRule = Rule::unique('users', 'email');
        if ($this->route('user') !== null) {
            $emailRule->ignore($this->route('user'));
        }

        return [
            'name' => [...($creating ? [] : ['sometimes']), 'required', 'string', 'max:120'],
            'email' => [...($creating ? [] : ['sometimes']), 'required', 'string', 'email', 'max:254', $emailRule],
            'password' => [
                ...($creating ? [] : ['sometimes']), 'required', 'string', 'min:12',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && (strlen($value) > 72 || str_contains($value, "\0"))) {
                        $fail('Kata sandi harus valid dan maksimal 72 byte.');
                    }
                },
            ],
            'active' => ['sometimes', 'required', 'boolean'],
            'role_codes' => [...($creating ? [] : ['sometimes']), 'required', 'array', 'list', 'min:1', 'max:8'],
            'role_codes.*' => ['required', 'string', 'distinct:strict', Rule::exists('roles', 'code')],
        ];
    }
}
