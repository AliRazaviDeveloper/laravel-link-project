<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Request\V1;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:254'],
            // No complexity rules on login: they would reject the passwords of
            // accounts created before a policy change, and leak the current policy.
            'password' => ['required', 'string'],
            'token_name' => ['sometimes', 'string', 'max:60'],
        ];
    }
}
