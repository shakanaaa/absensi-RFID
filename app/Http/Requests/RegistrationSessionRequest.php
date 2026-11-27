<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrationSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin, UserRole::PetugasPiket) ?? false;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;

        return [
            'class_id' => ['required', Rule::exists('classes', 'id')->where('school_id', $schoolId)],
            'reader_profile_id' => ['nullable', Rule::exists('reader_profiles', 'id')->where('is_active', true)],
        ];
    }
}
