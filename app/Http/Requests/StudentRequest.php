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
        $student = $this->route('student');

        return [
            'student_no' => [
                'required', 'string', 'max:50',
                Rule::unique('students', 'student_no')
                    ->where('school_id', $schoolId)
                    ->ignore($student?->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'gender' => ['nullable', 'in:male,female'],
            'birth_date' => ['nullable', 'date'],
            'status' => ['required', 'in:active,inactive,lulus'],
            // Keanggotaan kelas efektif (PRD FR-MST-002)
            'class_id' => ['required', Rule::exists('classes', 'id')->where('school_id', $schoolId)],
            'academic_year_id' => ['required', Rule::exists('academic_years', 'id')->where('school_id', $schoolId)],
            'from_date' => ['required', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'attendance_number' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
