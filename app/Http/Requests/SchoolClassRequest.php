<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(\App\Enums\UserRole::Admin) ?? false;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;
        $class = $this->route('class');

        return [
            'grade_id' => ['nullable', Rule::exists('grades', 'id')->where('school_id', $schoolId)],
            'concentration_id' => ['required', Rule::exists('concentrations', 'id')->where('school_id', $schoolId)],
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('classes', 'code')
                    ->where('school_id', $schoolId)
                    ->ignore($class?->id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'status' => ['required', 'in:active,inactive,archived'],
        ];
    }
}
