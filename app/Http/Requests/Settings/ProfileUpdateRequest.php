<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user?->id),
                function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                    if (! $user?->isClientUser()) {
                        return;
                    }

                    if (strtolower((string) $value) !== strtolower((string) $user->email)) {
                        $fail('Client accounts cannot change their sign-in email. Please submit an information update request instead.');
                    }
                },
            ],
        ];
    }
}
