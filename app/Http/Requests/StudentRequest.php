<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(\App\Enums\UserRole::Admin) ?? false;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'gender' => ['nullable', 'in:male,female'],
            'status' => ['required', 'in:active,inactive,lulus'],
            // Keanggotaan kelas efektif (PRD FR-MST-002)
            'class_id' => ['required', Rule::exists('classes', 'id')->where('school_id', $schoolId)],
            'academic_year_id' => ['required', Rule::exists('academic_years', 'id')->where('school_id', $schoolId)],
            'attendance_number' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
