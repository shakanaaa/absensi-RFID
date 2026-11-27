<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class UnassignCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin, UserRole::PetugasPiket) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
