<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin, UserRole::PetugasPiket) ?? false;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;

        return [
            'student_id' => ['required', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
