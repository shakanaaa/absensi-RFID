<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(\App\Enums\UserRole::Admin) ?? false;
    }

    public function rules(): array
    {
        $grade = $this->route('grade');

        return [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('grades', 'name')
                    ->where('school_id', $this->user()->school_id)
                    ->ignore($grade?->id),
            ],
            // Level ganda membuat promosi ambigu (nextGradeId mengambil yang pertama).
            'level' => [
                'nullable', 'integer', 'min:1', 'max:12',
                Rule::unique('grades', 'level')
                    ->where('school_id', $this->user()->school_id)
                    ->ignore($grade?->id),
            ],
        ];
    }
}
