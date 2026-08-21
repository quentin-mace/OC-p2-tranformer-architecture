<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Translation\PotentiallyTranslatedString;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'redirect_url' => ['required', 'url', $this->allowedOriginRule()],
        ];
    }

    private function allowedOriginRule(): ValidationRule
    {
        return new class implements ValidationRule
        {
            public function validate(string $attribute, mixed $value, \Closure $fail): void
            {
                $parts = parse_url($value);
                if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
                    $fail('Invalid redirect URL.');

                    return;
                }

                $origin = $parts['scheme'].'://'.$parts['host'];
                if (! empty($parts['port'])) {
                    $origin .= ':'.$parts['port'];
                }

                $allowed = config('auth.allowed_reset_hosts', []);
                if (! in_array($origin, $allowed, true)) {
                    $fail('Unauthorized redirect URL.');
                }
            }
        };
    }
}