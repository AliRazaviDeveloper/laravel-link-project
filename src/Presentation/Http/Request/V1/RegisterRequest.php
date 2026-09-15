<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Request\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Uniqueness is enforced by the database and reported by the domain, so
            // there is no `unique:` rule here — a validation lookup would be a second
            // source of truth that can disagree under concurrency.
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'name' => ['required', 'string', 'min:1', 'max:120'],
            'password' => ['required', 'string', Password::min(12)->uncompromised()],
            'token_name' => ['sometimes', 'string', 'max:60'],
        ];
    }
}
