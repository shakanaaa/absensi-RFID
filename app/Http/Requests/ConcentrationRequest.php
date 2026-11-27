<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConcentrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(\App\Enums\UserRole::Admin) ?? false;
    }

    public function rules(): array
    {
        $concentration = $this->route('concentration');

        return [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('concentrations', 'name')
                    ->where('school_id', $this->user()->school_id)
                    ->ignore($concentration?->id),
            ],
        ];
    }
}
