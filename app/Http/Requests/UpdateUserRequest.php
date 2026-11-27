<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(array_column(UserRole::cases(), 'value'))],
            'status' => ['required', 'in:active,inactive,suspended'],
        ];
    }
}
